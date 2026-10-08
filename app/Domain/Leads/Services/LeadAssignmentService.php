<?php

namespace App\Domain\Leads\Services;

use App\Domain\Audit\AuditEvent;
use App\Domain\Audit\AuditLogger;
use App\Domain\Leads\Enums\AssignmentType;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Models\CallAttempt;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeadAssignmentService
{
    /** Each dispatcher receives the same share of the batch (5/4/4 for 13 leads). */
    public const STRATEGY_ROUND_ROBIN = 'round_robin';

    /** Leads go to whoever has the fewest open leads, so workloads converge. */
    public const STRATEGY_BALANCED = 'balanced';

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Assigns (or unassigns when $dispatcher is null) a single lead and keeps
     * the assignment history.
     */
    public function assign(
        Lead $lead,
        ?User $dispatcher,
        ?User $by,
        AssignmentType $type = AssignmentType::MANUAL,
        ?string $reason = null,
    ): Lead {
        if ($dispatcher && (! $dispatcher->isDispatcher() || ! $dispatcher->isApproved())) {
            throw new BusinessRuleException('Le lead ne peut être assigné qu\'à un dispatcher actif.');
        }

        $fromId = $lead->assigned_to;
        $toId = $dispatcher?->id;

        if ($fromId === $toId) {
            return $lead;
        }

        return DB::transaction(function () use ($lead, $dispatcher, $by, $type, $reason, $fromId, $toId) {
            // The previous dispatcher loses access: close any call left open.
            if ($fromId) {
                CallAttempt::query()
                    ->where('lead_id', $lead->id)
                    ->where('dispatcher_id', $fromId)
                    ->open()
                    ->update(['ended_at' => now()]);
            }

            $lead->assigned_to = $toId;
            $lead->assigned_at = $toId ? now() : null;
            $lead->save();

            $assignment = LeadAssignment::create([
                'lead_id' => $lead->id,
                'from_user_id' => $fromId,
                'to_user_id' => $toId,
                'assigned_by' => $by?->id,
                'type' => $toId ? $type : AssignmentType::UNASSIGN,
                'reason' => $reason,
            ]);

            $event = match (true) {
                $toId === null => AuditEvent::LEAD_UNASSIGNED,
                $fromId === null => AuditEvent::LEAD_ASSIGNED,
                default => AuditEvent::LEAD_REASSIGNED,
            };

            $this->audit->log($event, $lead, $assignment, array_filter([
                'from' => $fromId,
                'to' => $toId,
                'to_name' => $dispatcher?->full_name,
                'type' => $assignment->type->value,
                'reason' => $reason,
            ]), $by);

            return $lead;
        });
    }

    /**
     * Distributes leads fairly between dispatchers.
     *
     * @param  Collection<int, Lead>  $leads
     * @param  Collection<int, User>  $dispatchers
     * @return array<int, int> dispatcher id => number of leads received
     */
    public function distribute(Collection $leads, Collection $dispatchers, ?User $by, string $strategy = self::STRATEGY_ROUND_ROBIN): array
    {
        $dispatchers = $dispatchers
            ->filter(fn (User $u) => $u->isDispatcher() && $u->isApproved())
            ->sortBy('id')
            ->values();

        if ($dispatchers->isEmpty()) {
            throw new BusinessRuleException('Aucun dispatcher actif disponible pour la distribution.');
        }

        $plan = $strategy === self::STRATEGY_BALANCED
            ? $this->balancedPlan($leads->count(), $dispatchers)
            : $this->roundRobinPlan($leads->count(), $dispatchers);

        $received = $dispatchers->mapWithKeys(fn (User $u) => [$u->id => 0])->all();
        $byId = $dispatchers->keyBy('id');

        DB::transaction(function () use ($leads, $plan, $byId, $by, $strategy, &$received) {
            foreach ($leads->values() as $index => $lead) {
                $dispatcherId = $plan[$index];
                $this->assign($lead, $byId[$dispatcherId], $by, AssignmentType::AUTO, "Distribution automatique ({$strategy})");
                $received[$dispatcherId]++;
            }
        });

        return $received;
    }

    /**
     * Strict rotation. The rotation resumes after the dispatcher who received
     * the last automatic assignment, so successive imports stay fair.
     *
     * @param  Collection<int, User>  $dispatchers
     * @return array<int, int> lead index => dispatcher id
     */
    public function roundRobinPlan(int $count, Collection $dispatchers): array
    {
        $ids = $dispatchers->pluck('id')->values()->all();

        $lastAutoId = LeadAssignment::query()
            ->where('type', AssignmentType::AUTO)
            ->whereIn('to_user_id', $ids)
            ->latest('id')
            ->value('to_user_id');

        $offset = $lastAutoId !== null ? (array_search($lastAutoId, $ids, true) + 1) % count($ids) : 0;

        $plan = [];
        for ($i = 0; $i < $count; $i++) {
            $plan[] = $ids[($offset + $i) % count($ids)];
        }

        return $plan;
    }

    /**
     * Least-loaded first (ties broken by id), based on currently open leads.
     *
     * @param  Collection<int, User>  $dispatchers
     * @return array<int, int> lead index => dispatcher id
     */
    public function balancedPlan(int $count, Collection $dispatchers): array
    {
        $ids = $dispatchers->pluck('id')->all();

        $loads = Lead::query()
            ->selectRaw('assigned_to, COUNT(*) as total')
            ->whereIn('assigned_to', $ids)
            ->whereIn('status', LeadStatus::open())
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to')
            ->map(fn ($v) => (int) $v)
            ->all();

        $load = [];
        foreach ($ids as $id) {
            $load[$id] = $loads[$id] ?? 0;
        }

        $plan = [];
        for ($i = 0; $i < $count; $i++) {
            $min = min($load);
            $target = array_search($min, $load, true);
            $plan[] = $target;
            $load[$target]++;
        }

        return $plan;
    }
}
