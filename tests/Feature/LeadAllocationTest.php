<?php

namespace Tests\Feature;

use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Users\Enums\UserStatus;
use App\Models\CallAttempt;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadAllocationTest extends TestCase
{
    use RefreshDatabase;

    private function newDispatcherPayload(array $extra = []): array
    {
        return [
            'first_name' => 'Nour',
            'last_name' => 'Ben Ali',
            'email' => 'nour@miraldrive.com',
            'phone' => '+21622333444',
            'password' => '123456789',
            'password_confirmation' => '123456789',
            ...$extra,
        ];
    }

    public function test_new_dispatcher_receives_the_requested_number_of_unassigned_leads(): void
    {
        Lead::factory()->count(8)->create();

        $response = $this->actingAs($this->admin())
            ->postJson('/api/v1/admin/dispatchers', $this->newDispatcherPayload(['initial_leads' => 5]))
            ->assertCreated()
            ->assertJsonPath('allocation.assigned', 5)
            ->assertJsonPath('allocation.from_pool', 5);

        $this->assertSame(5, Lead::where('assigned_to', $response->json('id'))->count());
        $this->assertSame(3, Lead::whereNull('assigned_to')->count());
    }

    public function test_leads_in_progress_or_already_processed_are_never_taken(): void
    {
        $other = $this->dispatcher();
        $untouched = Lead::factory()->count(2)->assignedTo($other->id)->create();

        // In progress (call made), qualified, NRP, and a PENDING lead that was already called.
        $inProgress = Lead::factory()->assignedTo($other->id)->create();
        $inProgress->forceFill(['status' => LeadStatus::IN_PROGRESS])->save();
        $qualified = Lead::factory()->assignedTo($other->id)->create();
        $qualified->forceFill(['status' => LeadStatus::QUALIFIED])->save();
        $nrp = Lead::factory()->assignedTo($other->id)->create();
        $nrp->forceFill(['status' => LeadStatus::NRP, 'nrp_attempts' => 1])->save();
        $called = Lead::factory()->assignedTo($other->id)->create();
        CallAttempt::create(['lead_id' => $called->id, 'dispatcher_id' => $other->id, 'attempt_number' => 1, 'started_at' => now()]);

        $response = $this->actingAs($this->admin())
            ->postJson('/api/v1/admin/dispatchers', $this->newDispatcherPayload(['initial_leads' => 10]))
            ->assertCreated()
            ->assertJsonPath('allocation.assigned', 2)
            ->assertJsonPath('allocation.from_others', 2);

        $newId = $response->json('id');
        $this->assertEqualsCanonicalizing($untouched->pluck('id')->all(), Lead::where('assigned_to', $newId)->pluck('id')->all());
        foreach ([$inProgress, $qualified, $nrp, $called] as $lead) {
            $this->assertSame($other->id, $lead->fresh()->assigned_to);
        }
        $this->assertDatabaseHas('lead_assignments', ['from_user_id' => $other->id, 'to_user_id' => $newId, 'type' => 'AUTO']);
    }

    public function test_rebalancing_takes_from_the_largest_untouched_backlog_and_can_be_disabled(): void
    {
        $busy = $this->dispatcher();
        $light = $this->dispatcher();
        Lead::factory()->count(6)->assignedTo($busy->id)->create();
        Lead::factory()->count(2)->assignedTo($light->id)->create();
        Lead::factory()->count(1)->create();
        $newcomer = $this->dispatcher();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/dispatchers/{$newcomer->id}/allocate", ['initial_leads' => 3, 'allow_rebalance' => false])
            ->assertOk()
            ->assertJsonPath('assigned', 1);

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/dispatchers/{$newcomer->id}/allocate", ['initial_leads' => 4])
            ->assertOk()
            ->assertJsonPath('from_others', 4);

        // 6/2 backlog -> the 4 leads all come from the busiest dispatcher.
        $this->assertSame(2, Lead::where('assigned_to', $busy->id)->count());
        $this->assertSame(2, Lead::where('assigned_to', $light->id)->count());
        $this->assertSame(5, Lead::where('assigned_to', $newcomer->id)->count());
    }

    public function test_approving_a_registration_can_allocate_leads(): void
    {
        Lead::factory()->count(3)->create();
        $pending = $this->dispatcher(UserStatus::PENDING);

        $this->actingAs($this->admin())
            ->postJson("/api/v1/admin/dispatchers/{$pending->id}/approve", ['initial_leads' => 2])
            ->assertOk()
            ->assertJsonPath('status', 'APPROVED')
            ->assertJsonPath('allocation.assigned', 2);
    }

    public function test_leads_cannot_be_allocated_to_an_inactive_dispatcher(): void
    {
        Lead::factory()->count(2)->create();
        $pending = $this->dispatcher(UserStatus::PENDING);

        $this->actingAs($this->admin())
            ->postJson("/api/v1/admin/dispatchers/{$pending->id}/allocate", ['initial_leads' => 2])
            ->assertUnprocessable();

        $this->assertSame(2, Lead::whereNull('assigned_to')->count());
    }

    public function test_allocatable_counts(): void
    {
        $d = $this->dispatcher();
        Lead::factory()->count(2)->create();
        Lead::factory()->count(3)->assignedTo($d->id)->create();
        Lead::factory()->assignedTo($d->id)->create()->forceFill(['status' => LeadStatus::QUALIFIED])->save();

        $this->actingAs($this->admin())->getJson('/api/v1/admin/leads/allocatable')
            ->assertOk()
            ->assertExactJson(['unassigned' => 2, 'reassignable' => 3]);

        $this->assertInstanceOf(User::class, $d);
    }
}
