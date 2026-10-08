<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Leads\Enums\AssignmentType;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Services\LeadAssignmentService;
use App\Domain\Leads\Services\LeadStatusService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignLeadsRequest;
use App\Http\Requests\Admin\DistributeLeadsRequest;
use App\Http\Requests\Admin\UpdateLeadStatusRequest;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Admin-only lead operations: assignment, distribution, status management.
 */
class LeadManagementController extends Controller
{
    public function __construct(private readonly LeadAssignmentService $assignments) {}

    public function assign(AssignLeadsRequest $request): array
    {
        $data = $request->validated();
        $dispatcher = $data['dispatcher_id'] ? User::findOrFail($data['dispatcher_id']) : null;

        $leads = Lead::whereIn('id', $data['lead_ids'])->get();

        DB::transaction(function () use ($leads, $dispatcher, $request, $data) {
            foreach ($leads as $lead) {
                $this->assignments->assign($lead, $dispatcher, $request->user(), AssignmentType::MANUAL, $data['reason'] ?? null);
            }
        });

        return ['updated' => $leads->count()];
    }

    public function distribute(DistributeLeadsRequest $request): array
    {
        $data = $request->validated();

        $dispatchers = User::query()->activeDispatchers()
            ->when($data['dispatcher_ids'] ?? null, fn ($q, $ids) => $q->whereIn('id', $ids))
            ->get();

        $leads = Lead::query()
            ->when(
                $data['lead_ids'] ?? null,
                fn ($q, $ids) => $q->whereIn('id', $ids),
                fn ($q) => $q->whereNull('assigned_to')->whereIn('status', LeadStatus::open()),
            )
            ->orderBy('source_created_at')
            ->orderBy('id')
            ->get();

        $received = $this->assignments->distribute($leads, $dispatchers, $request->user(), $data['strategy']);

        return [
            'distributed' => $leads->count(),
            'per_dispatcher' => $dispatchers->map(fn (User $u) => [
                'id' => $u->id,
                'full_name' => $u->full_name,
                'received' => $received[$u->id] ?? 0,
            ])->values(),
        ];
    }

    /**
     * Leads currently usable by an automatic allocation.
     */
    public function allocatable(): array
    {
        return $this->assignments->allocatable();
    }

    public function updateStatus(UpdateLeadStatusRequest $request, Lead $lead, LeadStatusService $statuses): LeadResource
    {
        $data = $request->validated();

        if ($request->boolean('reset_nrp')) {
            $lead->nrp_attempts = 0;
            $lead->last_nrp_at = null;
        }

        $statuses->change($lead, LeadStatus::from($data['status']), $request->user(), $data['reason'] ?? 'Modification administrateur');

        return new LeadResource($lead->fresh(['assignee', 'qualification']));
    }
}
