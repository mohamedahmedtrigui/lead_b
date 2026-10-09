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

    public function test_self_skips_level_3_and_is_b2c(): void
    {
        $dispatcher = $this->dispatcher();
        $lead = Lead::factory()->assignedTo($dispatcher->id)->create();
        $url = "/api/v1/leads/{$lead->id}/qualification/complete";
        $this->actingAs($dispatcher);

        // "Autre personne" without the level 3 answer is refused.
        $this->postJson($url, $this->answers(['beneficiary' => 'OTHER', 'transport_need' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors('transport_need');

        // "Pour lui-même": no level 3, the trip is personal (B2C).
        $this->postJson($url, $this->answers(['beneficiary' => 'SELF', 'transport_need' => null]))
            ->assertOk()
            ->assertJsonPath('qualification.transport_need', 'PERSONAL')
            ->assertJsonPath('qualification.is_b2b', false);
    }

    public function test_extra_routes_are_free_form_and_kept(): void
    {
        $dispatcher = $this->dispatcher();
        $lead = Lead::factory()->assignedTo($dispatcher->id)->create();

        $routes = [
            ['label' => 'Ali', 'departure' => 'Sakiet Ezzit', 'destination' => 'Usine', 'days' => ['MON', 'TUE'], 'arrival_time' => '07:45', 'return_time' => '16:30', 'note' => null],
            ['label' => 'Samedi', 'departure' => null, 'destination' => null, 'days' => ['SAT'], 'arrival_time' => '10:00', 'return_time' => null, 'note' => 'horaire réduit'],
        ];

        $this->actingAs($dispatcher)->patchJson("/api/v1/leads/{$lead->id}/qualification", [
            'arrival_time' => '08:00',
            'extra_routes' => $routes,
        ])->assertOk()
            ->assertJsonPath('arrival_time', '08:00')
            ->assertJsonPath('extra_routes.0.label', 'Ali')
            ->assertJsonPath('extra_routes.1.days.0', 'SAT');
    }

    public function test_other_apps_answers_only_kept_when_travelling_by_app(): void
    {
        app(ScriptService::class)->syncDefaults();
        $dispatcher = $this->dispatcher();
        $lead = Lead::factory()->assignedTo($dispatcher->id)->create();
        $this->actingAs($dispatcher);

        $this->postJson("/api/v1/leads/{$lead->id}/qualification/complete", $this->answers([
            'current_provider' => 'APPLICATION',
            'other_apps' => ['BOLT', 'YASSIR'],
            'other_apps_issues' => ['PRICE', 'CANCELLATIONS'],
            'other_apps_feedback' => 'Trop cher le matin',
        ]))->assertOk()
            ->assertJsonPath('qualification.other_apps_used', true)
            ->assertJsonPath('qualification.other_apps', ['BOLT', 'YASSIR']);

        $transcript = collect($lead->qualification()->first()->transcript)->keyBy('key');
        $details = collect($transcript['experience']['details'])->pluck(1)->join(' | ');
        $this->assertStringContainsString('Bolt, Yassir', $details);
        $this->assertStringContainsString('Trop cher le matin', $details);

        // Switching to a taxi drops the app answers.
        $lead2 = Lead::factory()->assignedTo($dispatcher->id)->create();
        $this->postJson("/api/v1/leads/{$lead2->id}/qualification/complete", $this->answers([
            'current_provider' => 'TRADITIONAL_TAXI',
            'other_apps' => ['BOLT'],
        ]))->assertOk()
            ->assertJsonPath('qualification.other_apps_used', false)
            ->assertJsonPath('qualification.other_apps', null);
    }

    public function test_several_next_actions_can_be_combined(): void
    {
        $dispatcher = $this->dispatcher();
        $this->actingAs($dispatcher);

        // Interested + quote + sales: qualified, the quote counts in the score.
        $lead = Lead::factory()->assignedTo($dispatcher->id)->create();
        $this->postJson("/api/v1/leads/{$lead->id}/qualification/complete", $this->answers([
            'next_action' => null,
            'wants_quotation' => null,
            'next_actions' => ['QUALIFIED', 'SEND_QUOTATION', 'TRANSFER_TO_SALES'],
        ]))->assertOk()
            ->assertJsonPath('lead.status', 'QUALIFIED')
            ->assertJsonPath('qualification.next_action', 'SEND_QUOTATION')
            ->assertJsonPath('qualification.next_actions', ['QUALIFIED', 'SEND_QUOTATION', 'TRANSFER_TO_SALES'])
            ->assertJsonPath('qualification.wants_quotation', true);

        // Quote + callback: the callback wins (status + date required).
        $lead2 = Lead::factory()->assignedTo($dispatcher->id)->create();
        $url = "/api/v1/leads/{$lead2->id}/qualification/complete";
        $this->postJson($url, $this->answers(['next_actions' => ['SEND_QUOTATION', 'CALLBACK']]))
            ->assertUnprocessable()->assertJsonValidationErrors('callback_at');
        $this->postJson($url, $this->answers([
            'next_actions' => ['SEND_QUOTATION', 'CALLBACK'],
            'callback_at' => now()->addDay()->toIso8601String(),
        ]))->assertOk()
            ->assertJsonPath('lead.status', 'CALLBACK')
            ->assertJsonPath('qualification.wants_callback', true);
    }

    public function test_not_interested_cannot_be_combined(): void
    {
        $dispatcher = $this->dispatcher();
        $lead = Lead::factory()->assignedTo($dispatcher->id)->create();

        $this->actingAs($dispatcher)->postJson("/api/v1/leads/{$lead->id}/qualification/complete", $this->answers([
            'next_actions' => ['NOT_INTERESTED', 'FOLLOW_UP'],
        ]))->assertUnprocessable()->assertJsonValidationErrors('next_actions');
    }

    public function test_company_size_is_free_text(): void
    {
        $dispatcher = $this->dispatcher();
        $lead = Lead::factory()->assignedTo($dispatcher->id)->create();

        $this->actingAs($dispatcher)->patchJson("/api/v1/leads/{$lead->id}/qualification", [
            'beneficiary' => 'OTHER',
            'transport_need' => 'EMPLOYEE',
            'company_name' => 'Poulina',
            'company_size' => 'environ 50',
        ])->assertOk()
            ->assertJsonPath('is_b2b', true)
            ->assertJsonPath('company_size', 'environ 50');
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
