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

    public const OPEN_LEADS_REDISTRIBUTE = 'redistribute';

    public const OPEN_LEADS_RELEASE = 'release';

    /**
     * Safe deletion: the account is archived (soft delete) so nothing it did
     * is lost — calls, notes, qualifications and audit stay attributed to it.
     *
     * - open leads (to do, in progress, callback, NRP) are redistributed
     *   round-robin to the other active dispatchers, or released to the pool;
     * - processed leads stay attributed to the archived account, or are
     *   transferred to $transferProcessedTo when given;
     * - sessions and tokens are revoked and the e-mail is freed.
     *
     * @return array{open_leads: int, redistributed: int, released: int, processed_leads: int, processed_transferred: int}
     */
    public function delete(User $user, User $by, string $openLeads = self::OPEN_LEADS_REDISTRIBUTE, ?User $transferProcessedTo = null): array
    {
        $this->ensureDispatcher($user);

        if ($transferProcessedTo && ($transferProcessedTo->is($user) || ! $transferProcessedTo->isDispatcher() || ! $transferProcessedTo->isApproved())) {
            throw new BusinessRuleException('Les leads traités ne peuvent être transférés qu\'à un autre dispatcher actif.');
        }

        return DB::transaction(function () use ($user, $by, $openLeads, $transferProcessedTo) {
            $reason = "Suppression du compte {$user->full_name}";
            $open = Lead::query()->where('assigned_to', $user->id)->whereIn('status', LeadStatus::open())->orderBy('id')->get();
            $processed = Lead::query()->where('assigned_to', $user->id)->whereNotIn('status', LeadStatus::open())->get();

            $result = [
                'open_leads' => $open->count(),
                'redistributed' => 0,
                'released' => 0,
                'processed_leads' => $processed->count(),
                'processed_transferred' => 0,
            ];

            // Others first, so the deleted account is not part of the rotation.
            $user->status = UserStatus::DEACTIVATED;
            $user->save();

            $others = User::query()->activeDispatchers()->whereKeyNot($user->id)->get();
            if ($openLeads === self::OPEN_LEADS_REDISTRIBUTE && $others->isNotEmpty() && $open->isNotEmpty()) {
                $this->assignments->distribute($open, $others, $by);
                $result['redistributed'] = $open->count();
            } else {
                foreach ($open as $lead) {
                    $this->assignments->assign($lead, null, $by, AssignmentType::UNASSIGN, $reason);
                }
                $result['released'] = $open->count();
            }

            if ($transferProcessedTo) {
                foreach ($processed as $lead) {
                    $this->assignments->assign($lead, $transferProcessedTo, $by, AssignmentType::MANUAL, $reason);
                }
                $result['processed_transferred'] = $processed->count();
            }

            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->tokens()->delete();

            // Free the e-mail address (unique) while keeping the name for history.
            $originalEmail = $user->email;
            $user->email = "deleted-{$user->id}-".now()->timestamp.'@archive.invalid';
            $user->save();
            $user->delete();

            $this->audit->log(AuditEvent::USER_DELETED, null, $user, ['email' => $originalEmail, ...$result], $by);

            return $result;
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
