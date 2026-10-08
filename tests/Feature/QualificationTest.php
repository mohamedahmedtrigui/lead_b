<?php

namespace Tests\Feature;

use App\Domain\Calls\Enums\CallOutcome;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Scripts\ScriptService;
use App\Models\CallAttempt;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QualificationTest extends TestCase
{
    use RefreshDatabase;

    private function answers(array $overrides = []): array
    {
        return array_merge([
            'call_availability' => 'YES',
            'beneficiary' => 'FAMILY',
            'transport_need' => 'RECURRING',
            'departure' => 'Sfax',
            'destination' => 'Centre-ville',
            'trip_type' => 'ROUND_TRIP',
            'departure_time' => '07:30',
            'return_time' => '17:30',
            'frequency' => 'DAILY',
            'days_of_week' => ['MON', 'TUE', 'WED', 'THU', 'FRI'],
            'trips_per_week' => 10,
            'is_recurring' => true,
            'passengers_count' => 2,
            'shared_transport' => 'YES',
            'shared_direction' => 'BOTH',
            'used_miraldrive' => 'NO',
            'current_provider' => 'TRADITIONAL_TAXI',
            'pain_point' => 'Coût élevé',
            'main_priority' => 'PUNCTUALITY',
            'recap_confirmed' => true,
            'wants_quotation' => true,
            'priority_stars' => 4,
            'summary_note' => 'Client recherche transport quotidien Sfax → centre-ville, 2 personnes, ouvert au partage.',
            'next_action' => 'SEND_QUOTATION',
        ], $overrides);
    }

    public function test_draft_save_computes_the_score_live(): void
    {
        $dispatcher = $this->dispatcher();
        $lead = Lead::factory()->assignedTo($dispatcher->id)->create();

        // daily 20 + recurring 15 + passengers 15 + shared 15 = 65 => WARM
        $this->actingAs($dispatcher)->patchJson("/api/v1/leads/{$lead->id}/qualification", [
            'frequency' => 'DAILY',
            'passengers_count' => 2,
            'shared_transport' => 'YES',
        ])->assertOk()
            ->assertJsonPath('interest_score', 65)
            ->assertJsonPath('interest_level', 'WARM')
            ->assertJsonPath('status', 'DRAFT');

        $this->assertDatabaseHas('audit_logs', ['lead_id' => $lead->id, 'event' => 'QUALIFICATION_STARTED']);
    }

    public function test_b2b_need_and_quotation_make_a_hot_lead(): void
    {
        $dispatcher = $this->dispatcher();
        $lead = Lead::factory()->assignedTo($dispatcher->id)->create();

        $this->actingAs($dispatcher)->patchJson("/api/v1/leads/{$lead->id}/qualification", [
            'beneficiary' => 'EMPLOYEES',
            'frequency' => 'DAILY',
            'estimated_passengers_per_trip' => 8,
            'wants_quotation' => true,
        ])->assertOk()
            ->assertJsonPath('is_b2b', true)
            ->assertJsonPath('interest_score', 80)
            ->assertJsonPath('interest_level', 'HOT');
    }

    public function test_completion_closes_the_call_and_qualifies_the_lead(): void
    {
        $dispatcher = $this->dispatcher();
        $lead = Lead::factory()->assignedTo($dispatcher->id)->create();
        $this->actingAs($dispatcher);

        $this->postJson("/api/v1/leads/{$lead->id}/calls")->assertOk();

        $this->postJson("/api/v1/leads/{$lead->id}/qualification/complete", $this->answers())
            ->assertOk()
            ->assertJsonPath('qualification.status', 'COMPLETED')
            // daily 20 + recurring 15 + passengers 15 + quotation 10
            // (shared transport is not proposed to a group of 2: answer dropped)
            ->assertJsonPath('qualification.interest_score', 60)
            ->assertJsonPath('qualification.interest_level', 'WARM')
            ->assertJsonPath('qualification.shared_transport', null)
            ->assertJsonPath('lead.status', 'QUALIFIED');

        $call = CallAttempt::where('lead_id', $lead->id)->sole();
        $this->assertSame(CallOutcome::CONNECTED, $call->outcome);
        $this->assertNotNull($call->ended_at);
        $this->assertDatabaseHas('dispatcher_notes', ['lead_id' => $lead->id, 'type' => 'SUMMARY']);
        $this->assertDatabaseHas('audit_logs', ['lead_id' => $lead->id, 'event' => 'QUALIFICATION_COMPLETED']);
    }

    public function test_completion_requires_the_essential_answers(): void
    {
        $dispatcher = $this->dispatcher();
        $lead = Lead::factory()->assignedTo($dispatcher->id)->create();

        $this->actingAs($dispatcher)->postJson("/api/v1/leads/{$lead->id}/qualification/complete", [
            'next_action' => 'QUALIFIED',
            'summary_note' => 'trop court',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['summary_note', 'beneficiary', 'departure', 'destination', 'frequency', 'priority_stars']);
    }

    public function test_recap_must_be_validated_and_shared_only_asked_to_a_single_person(): void
    {
        $dispatcher = $this->dispatcher();
        $lead = Lead::factory()->assignedTo($dispatcher->id)->create();
        $url = "/api/v1/leads/{$lead->id}/qualification/complete";
        $this->actingAs($dispatcher);

        $this->postJson($url, $this->answers(['recap_confirmed' => false]))
            ->assertUnprocessable()->assertJsonValidationErrors('recap_confirmed');

        // Single person: the shared transport answer is mandatory.
        $this->postJson($url, $this->answers(['passengers_count' => 1, 'shared_transport' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors('shared_transport');

        // Group of 2: not asked, so not required.
        $this->postJson($url, $this->answers(['shared_transport' => null, 'shared_direction' => null]))->assertOk();
    }

    public function test_passengers_are_capped_at_4(): void
    {
        $dispatcher = $this->dispatcher();
        $lead = Lead::factory()->assignedTo($dispatcher->id)->create();

        $this->actingAs($dispatcher)->patchJson("/api/v1/leads/{$lead->id}/qualification", ['passengers_count' => 5])
            ->assertUnprocessable()->assertJsonValidationErrors('passengers_count');
        $this->actingAs($dispatcher)->patchJson("/api/v1/leads/{$lead->id}/qualification", ['passengers_count' => 4])
            ->assertOk();
    }

    public function test_one_way_trip_is_only_shared_on_the_outbound_leg(): void
    {
        app(ScriptService::class)->syncDefaults();
        $dispatcher = $this->dispatcher();
        $lead = Lead::factory()->assignedTo($dispatcher->id)->create();

        // No direction needed (nor kept) for a one-way trip shared by a single person.
        $this->actingAs($dispatcher)->postJson("/api/v1/leads/{$lead->id}/qualification/complete", $this->answers([
            'trip_type' => 'ONE_WAY',
            'return_time' => null,
            'passengers_count' => 1,
            'shared_transport' => 'YES',
            'shared_direction' => 'BOTH',
        ]))->assertOk()->assertJsonPath('qualification.shared_direction', 'OUTBOUND');

        $transcript = collect($lead->qualification()->first()->transcript)->keyBy('key');
        $this->assertNull($transcript['shared']['reply']);
    }

    public function test_b2b_completion_requires_company_details(): void
    {
        $dispatcher = $this->dispatcher();
        $lead = Lead::factory()->assignedTo($dispatcher->id)->create();

        $this->actingAs($dispatcher)->postJson(
            "/api/v1/leads/{$lead->id}/qualification/complete",
            $this->answers(['beneficiary' => 'COMPANY', 'estimated_passengers_per_trip' => 5])
        )->assertUnprocessable()->assertJsonValidationErrors(['company_name', 'decision_role']);
    }

    public function test_not_interested_only_needs_the_closing_fields(): void
    {
        $dispatcher = $this->dispatcher();
        $lead = Lead::factory()->assignedTo($dispatcher->id)->create();

        $this->actingAs($dispatcher)->postJson("/api/v1/leads/{$lead->id}/qualification/complete", [
            'next_action' => 'NOT_INTERESTED',
            'summary_note' => 'Le client a déjà une solution et ne souhaite pas changer.',
        ])->assertOk()->assertJsonPath('lead.status', LeadStatus::NOT_INTERESTED->value);
    }
}
