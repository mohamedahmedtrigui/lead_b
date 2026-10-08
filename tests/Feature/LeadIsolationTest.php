<?php

namespace Tests\Feature;

use App\Domain\Leads\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatcher_only_lists_own_leads(): void
    {
        $alice = $this->dispatcher();
        $bob = $this->dispatcher();
        Lead::factory()->count(3)->assignedTo($alice->id)->create();
        Lead::factory()->count(2)->assignedTo($bob->id)->create();
        Lead::factory()->create();

        $this->actingAs($alice)->getJson('/api/v1/leads?assigned_to=none')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_leads_are_sorted_by_status_importance_by_default(): void
    {
        $dispatcher = $this->dispatcher();
        $make = fn (LeadStatus $status, array $extra = []) => tap(
            Lead::factory()->assignedTo($dispatcher->id)->create(),
            fn (Lead $lead) => $lead->forceFill(['status' => $status, ...$extra])->save(),
        );

        $make(LeadStatus::QUALIFIED);
        $make(LeadStatus::INVALID);
        $make(LeadStatus::CALLBACK, ['callback_at' => now()->addDays(2)]);
        $make(LeadStatus::IN_PROGRESS);
        $make(LeadStatus::CALLBACK, ['callback_at' => now()->addHour()]);
        $make(LeadStatus::NRP);
        $make(LeadStatus::PENDING);

        $rows = $this->actingAs($dispatcher)->getJson('/api/v1/leads')->assertOk()->json('data');

        $this->assertSame(
            ['PENDING', 'IN_PROGRESS', 'CALLBACK', 'CALLBACK', 'NRP', 'QUALIFIED', 'INVALID'],
            array_column($rows, 'status'),
        );
        // Soonest callback first.
        $this->assertLessThan($rows[3]['callback_at'], $rows[2]['callback_at']);
    }

    public function test_dispatcher_cannot_access_another_dispatchers_lead_by_id(): void
    {
        $alice = $this->dispatcher();
        $bob = $this->dispatcher();
        $lead = Lead::factory()->assignedTo($bob->id)->create();

        $this->actingAs($alice);
        $this->getJson("/api/v1/leads/{$lead->id}")->assertNotFound();
        $this->getJson("/api/v1/leads/{$lead->id}/timeline")->assertNotFound();
        $this->getJson("/api/v1/leads/{$lead->id}/qualification")->assertNotFound();
        $this->postJson("/api/v1/leads/{$lead->id}/calls")->assertNotFound();
        $this->postJson("/api/v1/leads/{$lead->id}/calls/outcome", ['outcome' => 'NO_ANSWER'])->assertNotFound();
        $this->patchJson("/api/v1/leads/{$lead->id}/qualification", ['beneficiary' => 'SELF'])->assertNotFound();
        $this->postJson("/api/v1/leads/{$lead->id}/notes", ['body' => 'Hello'])->assertNotFound();

        $this->assertDatabaseCount('call_attempts', 0);
        $this->assertDatabaseCount('lead_qualifications', 0);
    }

    public function test_admin_sees_all_leads_but_cannot_qualify(): void
    {
        $admin = $this->admin();
        $lead = Lead::factory()->assignedTo($this->dispatcher()->id)->create();

        $this->actingAs($admin)->getJson("/api/v1/leads/{$lead->id}")->assertOk();
        $this->actingAs($admin)->postJson("/api/v1/leads/{$lead->id}/calls")->assertForbidden();
    }
}
