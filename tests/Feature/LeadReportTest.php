<?php

namespace Tests\Feature;

use App\Domain\Scripts\ScriptService;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ScriptService::class)->syncDefaults();
    }

    private function qualify(Lead $lead): void
    {
        $this->postJson("/api/v1/leads/{$lead->id}/calls")->assertOk();
        $this->postJson("/api/v1/leads/{$lead->id}/qualification/complete", [
            'call_availability' => 'YES',
            'beneficiary' => 'SELF',
            'transport_need' => 'RECURRING',
            'departure' => 'Sfax',
            'destination' => 'Centre-ville',
            'trip_type' => 'ROUND_TRIP',
            'departure_time' => '07:30',
            'return_time' => '17:30',
            'frequency' => 'DAILY',
            'days_of_week' => ['MON', 'TUE'],
            'passengers_count' => 1,
            'shared_transport' => 'NO',
            'used_miraldrive' => 'NO',
            'current_provider' => 'TRADITIONAL_TAXI',
            'pain_point' => 'Trop cher',
            'recap_confirmed' => true,
            'priority_stars' => 3,
            'summary_note' => 'Client seul, Sfax vers centre-ville tous les jours, préfère un trajet individuel.',
            'next_action' => 'QUALIFIED',
        ])->assertOk();
    }

    public function test_completion_stores_the_conversation_with_placeholders_filled(): void
    {
        $dispatcher = $this->dispatcher();
        $dispatcher->forceFill(['first_name' => 'Ines'])->save();
        $lead = Lead::factory()->assignedTo($dispatcher->id)->create();
        $this->actingAs($dispatcher);

        $this->qualify($lead);

        $transcript = collect($lead->qualification()->first()->transcript)->keyBy('key');

        $this->assertStringContainsString('ena Ines men MiralDrive', $transcript['introduction']['said'][0]);
        $this->assertSame('Oui', $transcript['introduction']['answer']);
        $this->assertSame('Behy, merci barcha. Nbdéw.', $transcript['introduction']['reply']);
        $this->assertStringContainsString('men Sfax lel Centre-ville', $transcript['recap']['question']);
        $this->assertStringContainsString('trajet individuel', $transcript['recap']['question']);
        $this->assertSame('Non', $transcript['shared']['answer']);
        $this->assertArrayNotHasKey('b2b', $transcript->all());
    }

    public function test_owner_and_admin_can_download_the_pdf_but_not_other_dispatchers(): void
    {
        $owner = $this->dispatcher();
        $lead = Lead::factory()->assignedTo($owner->id)->create();
        $this->actingAs($owner);
        $this->qualify($lead);

        $response = $this->get("/api/v1/leads/{$lead->id}/report")->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $this->actingAs($this->admin())->get("/api/v1/leads/{$lead->id}/report?download=1")
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="fiche-lead-'.$lead->id.'-'.str($lead->name)->slug().'.pdf"');

        $this->actingAs($this->dispatcher())->getJson("/api/v1/leads/{$lead->id}/report")->assertNotFound();

        $this->assertDatabaseHas('audit_logs', ['lead_id' => $lead->id, 'event' => 'LEAD_REPORT_EXPORTED']);
    }

    public function test_pdf_is_available_for_a_lead_never_qualified(): void
    {
        $lead = Lead::factory()->create();

        $this->actingAs($this->admin())->get("/api/v1/leads/{$lead->id}/report")->assertOk();
    }
}
