<?php

namespace Tests\Feature;

use App\Domain\Leads\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_nrp_requires_spaced_attempts_before_becoming_final(): void
    {
        config(['leads.nrp.max_attempts' => 3, 'leads.nrp.min_interval_minutes' => 30]);
        $dispatcher = $this->dispatcher();
        $lead = Lead::factory()->assignedTo($dispatcher->id)->create();
        $this->actingAs($dispatcher);

        $nrp = fn () => $this->postJson("/api/v1/leads/{$lead->id}/calls/outcome", ['outcome' => 'NO_ANSWER']);

        $nrp()->assertOk()->assertJsonPath('nrp.attempts', 1)->assertJsonPath('nrp.final', false);
        $nrp()->assertUnprocessable()->assertJsonPath('code', 'nrp_too_soon');

        $this->travel(31)->minutes();
        $nrp()->assertOk()->assertJsonPath('nrp.attempts', 2);

        $this->travel(31)->minutes();
        $nrp()->assertOk()->assertJsonPath('nrp.attempts', 3)->assertJsonPath('nrp.final', true);

        $this->travel(31)->minutes();
        $nrp()->assertUnprocessable()->assertJsonPath('code', 'nrp_final');
        $this->postJson("/api/v1/leads/{$lead->id}/calls")->assertUnprocessable();

        $this->assertSame(LeadStatus::NRP, $lead->fresh()->status);
        $this->assertDatabaseCount('call_attempts', 3);
    }

    public function test_starting_a_call_sets_lead_in_progress_and_is_idempotent(): void
    {
        $dispatcher = $this->dispatcher();
        $lead = Lead::factory()->assignedTo($dispatcher->id)->create();
        $this->actingAs($dispatcher);

        $first = $this->postJson("/api/v1/leads/{$lead->id}/calls")->assertOk()->json('call.id');
        $second = $this->postJson("/api/v1/leads/{$lead->id}/calls")->assertOk()->json('call.id');

        $this->assertSame($first, $second);
        $this->assertSame(LeadStatus::IN_PROGRESS, $lead->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['lead_id' => $lead->id, 'event' => 'CALL_STARTED']);
    }

    public function test_callback_requires_a_future_date(): void
    {
        $dispatcher = $this->dispatcher();
        $lead = Lead::factory()->assignedTo($dispatcher->id)->create();
        $this->actingAs($dispatcher);

        $this->postJson("/api/v1/leads/{$lead->id}/calls/outcome", ['outcome' => 'CALLBACK_REQUESTED'])
            ->assertUnprocessable()->assertJsonValidationErrors('callback_at');

        $this->postJson("/api/v1/leads/{$lead->id}/calls/outcome", [
            'outcome' => 'CALLBACK_REQUESTED',
            'callback_at' => now()->addDay()->toIso8601String(),
            'note' => 'Rappeler demain matin',
        ])->assertOk()->assertJsonPath('status', 'CALLBACK');

        $this->assertDatabaseHas('audit_logs', ['lead_id' => $lead->id, 'event' => 'CALLBACK_SCHEDULED']);
        $this->assertDatabaseHas('dispatcher_notes', ['lead_id' => $lead->id, 'body' => 'Rappeler demain matin']);
    }
}
