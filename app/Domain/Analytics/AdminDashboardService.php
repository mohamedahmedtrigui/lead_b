<?php

namespace App\Domain\Analytics;

use App\Domain\Calls\Enums\CallOutcome;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Qualification\Enums\InterestLevel;
use App\Domain\Qualification\Enums\NextAction;
use App\Domain\Qualification\Enums\QualificationStatus;
use App\Domain\Users\Enums\UserStatus;
use App\Models\CallAttempt;
use App\Models\Lead;
use App\Models\LeadQualification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AdminDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $byStatus = Lead::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($v) => (int) $v);

        $byLevel = LeadQualification::query()
            ->where('status', QualificationStatus::COMPLETED)
            ->selectRaw('interest_level, COUNT(*) as total')
            ->groupBy('interest_level')
            ->pluck('total', 'interest_level')
            ->map(fn ($v) => (int) $v);

        $status = fn (LeadStatus $s) => $byStatus[$s->value] ?? 0;
        $level = fn (InterestLevel $l) => $byLevel[$l->value] ?? 0;

        $total = (int) $byStatus->sum();
        $contacted = Lead::query()->whereHas('callAttempts')->count();

        return [
            'stats' => [
                'total' => $total,
                'assigned' => Lead::query()->whereNotNull('assigned_to')->count(),
                'unassigned' => Lead::query()->whereNull('assigned_to')->count(),
                'pending' => $status(LeadStatus::PENDING),
                'in_progress' => $status(LeadStatus::IN_PROGRESS),
                'callback' => $status(LeadStatus::CALLBACK),
                'contacted' => $contacted,
                'qualified' => $status(LeadStatus::QUALIFIED),
                'hot' => $level(InterestLevel::HOT),
                'warm' => $level(InterestLevel::WARM),
                'nrp' => $status(LeadStatus::NRP),
                'not_interested' => $status(LeadStatus::NOT_INTERESTED),
                'invalid' => $status(LeadStatus::INVALID),
                'converted' => $status(LeadStatus::CONVERTED),
            ],
            'by_status' => $byStatus,
            'by_interest' => $byLevel,
            'today' => $this->callsToday(),
            'funnel' => $this->funnel($total, $contacted),
            'performance' => $this->performance(),
            'pending_dispatchers' => User::query()->dispatchers()->where('status', UserStatus::PENDING)->count(),
        ];
    }

    /**
     * Leads -> Contacted -> Conversations -> Qualified -> HOT -> Quotation -> Converted
     *
     * @return array<int, array{key: string, count: int, rate: float}>
     */
    private function funnel(int $total, int $contacted): array
    {
        $completed = fn (Builder $q) => $q->where('status', QualificationStatus::COMPLETED);

        $steps = [
            'leads' => $total,
            'contacted' => $contacted,
            'conversations' => Lead::query()
                ->whereHas('callAttempts', fn ($q) => $q->whereIn('outcome', CallOutcome::reachedValues()))
                ->count(),
            'qualified' => Lead::query()->whereHas('qualification', $completed)->count(),
            'hot' => Lead::query()
                ->whereHas('qualification', fn ($q) => $completed($q)->where('interest_level', InterestLevel::HOT))
                ->count(),
            'quotation' => Lead::query()
                ->whereHas('qualification', fn ($q) => $completed($q)->where(fn ($q) => $q
                    ->where('next_action', NextAction::SEND_QUOTATION)
                    ->orWhere('wants_quotation', true)))
                ->count(),
            'converted' => Lead::query()->where('status', LeadStatus::CONVERTED)->count(),
        ];

        return collect($steps)->map(fn ($count, $key) => [
            'key' => $key,
            'count' => $count,
            'rate' => $total > 0 ? round($count / $total * 100, 1) : 0.0,
        ])->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function performance(): array
    {
        $completed = fn (Builder $q) => $q->where('status', QualificationStatus::COMPLETED);

        return User::query()
            ->dispatchers()
            ->whereIn('status', [UserStatus::APPROVED, UserStatus::DEACTIVATED])
            ->withCount([
                'assignedLeads as assigned',
                'callAttempts as calls',
                'callAttempts as connected' => fn ($q) => $q->whereIn('outcome', CallOutcome::reachedValues()),
                'assignedLeads as qualified' => fn ($q) => $q->whereHas('qualification', $completed),
                'assignedLeads as hot' => fn ($q) => $q->whereHas('qualification', fn ($q) => $completed($q)->where('interest_level', InterestLevel::HOT)),
                'assignedLeads as nrp' => fn ($q) => $q->where('status', LeadStatus::NRP),
                'assignedLeads as converted' => fn ($q) => $q->where('status', LeadStatus::CONVERTED),
            ])
            ->withAvg(['callAttempts as avg_call_seconds' => fn ($q) => $q->where('outcome', CallOutcome::CONNECTED)], 'duration_seconds')
            ->orderBy('first_name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->full_name,
                'status' => $u->status->value,
                'assigned' => (int) $u->assigned,
                'calls' => (int) $u->calls,
                'connected' => (int) $u->connected,
                'qualified' => (int) $u->qualified,
                'hot' => (int) $u->hot,
                'nrp' => (int) $u->nrp,
                'converted' => (int) $u->converted,
                'connection_rate' => $u->calls > 0 ? round($u->connected / $u->calls * 100, 1) : 0.0,
                'conversion_rate' => $u->assigned > 0 ? round($u->converted / $u->assigned * 100, 1) : 0.0,
                'qualification_rate' => $u->assigned > 0 ? round($u->qualified / $u->assigned * 100, 1) : 0.0,
                'avg_call_seconds' => $u->avg_call_seconds !== null ? (int) round($u->avg_call_seconds) : null,
            ])
            ->all();
    }

    /**
     * @return array<string, int>
     */
    public function callsToday(): array
    {
        $today = CallAttempt::query()->where('started_at', '>=', now()->startOfDay());

        return [
            'calls' => (clone $today)->count(),
            'connected' => (clone $today)->whereIn('outcome', CallOutcome::reachedValues())->count(),
        ];
    }
}
