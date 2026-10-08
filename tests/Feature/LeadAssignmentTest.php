<?php

namespace Tests\Feature;

use App\Domain\Users\Enums\UserStatus;
use App\Models\Lead;
use App\Models\LeadAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_round_robin_splits_13_leads_into_5_4_4(): void
    {
        $admin = $this->admin();
        $dispatchers = collect([$this->dispatcher(), $this->dispatcher(), $this->dispatcher()]);
        $this->dispatcher(UserStatus::PENDING); // must be ignored
        Lead::factory()->count(13)->create();

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/leads/distribute', ['strategy' => 'round_robin'])
            ->assertOk()
            ->assertJsonPath('distributed', 13);

        $counts = collect($response->json('per_dispatcher'))->pluck('received')->sort()->values()->all();
        $this->assertSame([4, 4, 5], $counts);
        $this->assertSame(13, LeadAssignment::count());
        $this->assertSame(0, Lead::whereNull('assigned_to')->count());
        $dispatchers->each(fn ($d) => $this->assertGreaterThanOrEqual(4, Lead::where('assigned_to', $d->id)->count()));
    }

    public function test_balanced_strategy_fills_the_least_loaded_dispatcher_first(): void
    {
        $admin = $this->admin();
        $busy = $this->dispatcher();
        $free = $this->dispatcher();
        Lead::factory()->count(4)->assignedTo($busy->id)->create();
        Lead::factory()->count(6)->create();

        $this->actingAs($admin)->postJson('/api/v1/admin/leads/distribute', ['strategy' => 'balanced'])->assertOk();

        $this->assertSame(5, Lead::where('assigned_to', $free->id)->count());
        $this->assertSame(5, Lead::where('assigned_to', $busy->id)->count());
    }

    public function test_manual_reassignment_is_recorded_in_history(): void
    {
        $admin = $this->admin();
        $from = $this->dispatcher();
        $to = $this->dispatcher();
        $lead = Lead::factory()->assignedTo($from->id)->create();

        $this->actingAs($admin)->postJson('/api/v1/admin/leads/assign', [
            'lead_ids' => [$lead->id],
            'dispatcher_id' => $to->id,
            'reason' => 'Congé',
        ])->assertOk();

        $this->assertSame($to->id, $lead->fresh()->assigned_to);
        $this->assertDatabaseHas('lead_assignments', [
            'lead_id' => $lead->id, 'from_user_id' => $from->id, 'to_user_id' => $to->id, 'type' => 'MANUAL',
        ]);
        $this->assertDatabaseHas('audit_logs', ['lead_id' => $lead->id, 'event' => 'LEAD_REASSIGNED']);
    }

    public function test_deactivating_a_dispatcher_releases_open_leads(): void
    {
        $admin = $this->admin();
        $dispatcher = $this->dispatcher();
        Lead::factory()->count(2)->assignedTo($dispatcher->id)->create();

        $this->actingAs($admin)->postJson("/api/v1/admin/dispatchers/{$dispatcher->id}/deactivate")->assertOk();

        $this->assertSame(0, Lead::where('assigned_to', $dispatcher->id)->count());
    }
}
