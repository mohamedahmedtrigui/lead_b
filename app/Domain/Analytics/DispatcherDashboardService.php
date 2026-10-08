<?php

namespace App\Domain\Analytics;

use App\Domain\Calls\Enums\CallOutcome;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Qualification\Enums\InterestLevel;
use App\Domain\Qualification\Enums\QualificationStatus;
use App\Models\CallAttempt;
use App\Models\Lead;
use App\Models\User;

class DispatcherDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function overview(User $dispatcher): array
    {
        $leads = Lead::query()->where('assigned_to', $dispatcher->id);

        $byStatus = (clone $leads)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($v) => (int) $v);

        $status = fn (LeadStatus $s) => $byStatus[$s->value] ?? 0;

        $callsToday = CallAttempt::query()
            ->where('dispatcher_id', $dispatcher->id)
            ->where('started_at', '>=', now()->startOfDay());

        return [
            'stats' => [
                'total' => (int) $byStatus->sum(),
                'pending' => $status(LeadStatus::PENDING),
                'in_progress' => $status(LeadStatus::IN_PROGRESS),
                'callback' => $status(LeadStatus::CALLBACK),
                'hot' => (clone $leads)->whereHas('qualification', fn ($q) => $q
                    ->where('status', QualificationStatus::COMPLETED)
                    ->where('interest_level', InterestLevel::HOT))->count(),
                'qualified' => $status(LeadStatus::QUALIFIED),
                'nrp' => $status(LeadStatus::NRP),
                'not_interested' => $status(LeadStatus::NOT_INTERESTED),
                'converted' => $status(LeadStatus::CONVERTED),
                'calls_today' => (clone $callsToday)->count(),
                'connected_today' => (clone $callsToday)->whereIn('outcome', CallOutcome::reachedValues())->count(),
            ],
            'callbacks_due' => (clone $leads)
                ->where('status', LeadStatus::CALLBACK)
                ->whereNotNull('callback_at')
                ->where('callback_at', '<=', now()->endOfDay())
                ->orderBy('callback_at')
                ->limit(10)
                ->get(['id', 'name', 'phone', 'callback_at'])
                ->map(fn (Lead $l) => [
                    'id' => $l->id,
                    'name' => $l->name,
                    'phone' => $l->phone,
                    'callback_at' => $l->callback_at?->toIso8601String(),
                    'overdue' => $l->callback_at?->isPast() ?? false,
                ]),
        ];
    }
}
