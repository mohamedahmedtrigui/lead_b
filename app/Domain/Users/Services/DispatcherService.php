<?php

namespace App\Domain\Users\Services;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLogger;
use App\Domain\Leads\Enums\AssignmentType;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Services\LeadAssignmentService;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Domain\Users\Enums\UserRole;
use App\Domain\Users\Enums\UserStatus;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DispatcherService
{
    public function __construct(
        private readonly LeadAssignmentService $assignments,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Self-registration: the account stays PENDING until an admin approves it.
     *
     * @param  array<string, mixed>  $data
     */
    public function register(array $data): User
    {
        $user = $this->make($data, UserStatus::PENDING);
        $this->audit->log(AuditEvent::USER_REGISTERED, null, $user, ['email' => $user->email], $user);

        return $user;
    }

    /**
     * Account created by an admin: approved immediately.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $by): User
    {
        $user = $this->make($data, UserStatus::APPROVED, $by);
        $this->audit->log(AuditEvent::USER_APPROVED, null, $user, ['email' => $user->email, 'created_by_admin' => true], $by);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        $user->fill(array_filter($data, fn ($v, $k) => $k !== 'password' || filled($v), ARRAY_FILTER_USE_BOTH));
        $user->save();

        return $user;
    }

    public function approve(User $user, User $by): User
    {
        $this->ensureDispatcher($user);
        $user->status = UserStatus::APPROVED;
        $user->approved_by = $by->id;
        $user->approved_at = now();
        $user->save();

        $this->audit->log(AuditEvent::USER_APPROVED, null, $user, ['email' => $user->email], $by);

        return $user;
    }

    public function reject(User $user, User $by): User
    {
        $this->ensureDispatcher($user);
        if ($user->status !== UserStatus::PENDING) {
            throw new BusinessRuleException('Seule une inscription en attente peut être rejetée.');
        }

        $user->status = UserStatus::REJECTED;
        $user->save();

        $this->audit->log(AuditEvent::USER_REJECTED, null, $user, ['email' => $user->email], $by);

        return $user;
    }

    /**
     * Deactivates the account, kills its sessions and (optionally) releases its
     * open leads back to the unassigned pool for redistribution.
     */
    public function deactivate(User $user, User $by, bool $releaseLeads = true): User
    {
        $this->ensureDispatcher($user);

        return DB::transaction(function () use ($user, $by, $releaseLeads) {
            $user->status = UserStatus::DEACTIVATED;
            $user->save();

            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->tokens()->delete();

            $released = 0;
            if ($releaseLeads) {
                Lead::query()
                    ->where('assigned_to', $user->id)
                    ->whereIn('status', LeadStatus::open())
                    ->get()
                    ->each(function (Lead $lead) use ($by, &$released) {
                        $this->assignments->assign($lead, null, $by, AssignmentType::UNASSIGN, 'Dispatcher désactivé');
                        $released++;
                    });
            }

            $this->audit->log(AuditEvent::USER_DEACTIVATED, null, $user, ['email' => $user->email, 'released_leads' => $released], $by);

            return $user;
        });
    }

    public function reactivate(User $user, User $by): User
    {
        $this->ensureDispatcher($user);
        $user->status = UserStatus::APPROVED;
        $user->approved_by ??= $by->id;
        $user->approved_at ??= now();
        $user->save();

        $this->audit->log(AuditEvent::USER_REACTIVATED, null, $user, ['email' => $user->email], $by);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function make(array $data, UserStatus $status, ?User $by = null): User
    {
        $user = new User([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
        ]);
        $user->role = UserRole::DISPATCHER;
        $user->status = $status;
        if ($status === UserStatus::APPROVED) {
            $user->approved_by = $by?->id;
            $user->approved_at = now();
        }
        $user->save();

        return $user;
    }

    private function ensureDispatcher(User $user): void
    {
        if (! $user->isDispatcher()) {
            throw new BusinessRuleException('Cette action ne concerne que les comptes dispatcher.');
        }
    }
}
