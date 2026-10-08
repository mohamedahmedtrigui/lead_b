<?php

namespace Tests\Feature;

use App\Domain\Leads\Enums\LeadStatus;
use App\Models\CallAttempt;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DispatcherDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function deleteDispatcher(User $admin, User $user, array $payload = []): TestResponse
    {
        return $this->actingAs($admin)->deleteJson("/api/v1/admin/dispatchers/{$user->id}", [
            'open_leads' => 'redistribute',
            'confirmation' => 'SUPPRIMER',
            ...$payload,
        ]);
    }

    /**
     * @return array{0: User, 1: User, 2: Collection<int, Lead>, 3: Lead}
     */
    private function scenario(): array
    {
        $leaving = $this->dispatcher();
        $open = Lead::factory()->count(3)->assignedTo($leaving->id)->create();
        $processed = Lead::factory()->assignedTo($leaving->id)->create();
        $processed->forceFill(['status' => LeadStatus::QUALIFIED])->save();
        $processed->qualification()->create(['departure' => 'Sfax']);
        CallAttempt::create(['lead_id' => $processed->id, 'dispatcher_id' => $leaving->id, 'attempt_number' => 1, 'started_at' => now(), 'ended_at' => now(), 'outcome' => 'CONNECTED']);

        return [$leaving, $this->dispatcher(), $open, $processed];
    }

    public function test_deleting_redistributes_open_leads_and_keeps_processed_history(): void
    {
        [$leaving, $colleague, $open, $processed] = $this->scenario();
        $email = $leaving->email;

        $this->deleteDispatcher($this->admin(), $leaving)
            ->assertOk()
            ->assertJsonPath('open_leads', 3)
            ->assertJsonPath('redistributed', 3)
            ->assertJsonPath('processed_leads', 1);

        $this->assertSoftDeleted('users', ['id' => $leaving->id]);
        $this->assertSame(3, Lead::whereIn('id', $open->pluck('id'))->where('assigned_to', $colleague->id)->count());

        // Processed lead untouched: still attributed, qualification and call kept.
        $processed->refresh();
        $this->assertSame($leaving->id, $processed->assigned_to);
        $this->assertSame('Sfax', $processed->qualification()->first()->departure);
        $this->assertSame(1, CallAttempt::where('dispatcher_id', $leaving->id)->count());
        $this->assertStringEndsWith('(supprimé)', $processed->assignee->full_name);

        // Cannot log in anymore, and the e-mail is free again.
        $this->spa()->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'Password1'])->assertUnprocessable();
        $this->spa()->postJson('/api/v1/auth/register', [
            'first_name' => 'New', 'last_name' => 'Person', 'email' => $email, 'phone' => '+21622333444',
            'password' => '123456789', 'password_confirmation' => '123456789',
        ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', ['event' => 'USER_DELETED']);
    }

    public function test_open_leads_can_be_released_and_processed_leads_transferred(): void
    {
        [$leaving, $colleague, $open, $processed] = $this->scenario();

        $this->deleteDispatcher($this->admin(), $leaving, ['open_leads' => 'release', 'transfer_processed_to' => $colleague->id])
            ->assertOk()
            ->assertJsonPath('released', 3)
            ->assertJsonPath('processed_transferred', 1);

        $this->assertSame(3, Lead::whereIn('id', $open->pluck('id'))->whereNull('assigned_to')->count());
        $this->assertSame($colleague->id, $processed->fresh()->assigned_to);
    }

    public function test_deletion_requires_typed_confirmation_and_only_targets_dispatchers(): void
    {
        $admin = $this->admin();
        $dispatcher = $this->dispatcher();

        $this->deleteDispatcher($admin, $dispatcher, ['confirmation' => 'oui'])->assertUnprocessable()->assertJsonValidationErrors('confirmation');
        $this->deleteDispatcher($admin, $this->admin())->assertUnprocessable();
        $this->actingAs($dispatcher)->deleteJson("/api/v1/admin/dispatchers/{$dispatcher->id}", [])->assertForbidden();

        $this->assertNotSoftDeleted('users', ['id' => $dispatcher->id]);
    }

    public function test_archived_dispatcher_stays_in_performance_and_out_of_assignment_lists(): void
    {
        [$leaving] = $this->scenario();
        $admin = $this->admin();
        $this->deleteDispatcher($admin, $leaving)->assertOk();

        $performance = collect($this->actingAs($admin)->getJson('/api/v1/admin/dashboard')->json('performance'));
        $this->assertTrue($performance->firstWhere('id', $leaving->id)['deleted']);

        $this->assertNotContains($leaving->id, collect($this->getJson('/api/v1/admin/dispatchers')->json())->pluck('id'));
        $this->deleteJson("/api/v1/admin/dispatchers/{$leaving->id}", ['open_leads' => 'release', 'confirmation' => 'SUPPRIMER'])->assertNotFound();
    }
}
