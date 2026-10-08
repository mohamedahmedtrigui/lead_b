<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Leads\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\LeadIndexRequest;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\CallAttemptResource;
use App\Http\Resources\DispatcherNoteResource;
use App\Http\Resources\LeadAssignmentResource;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use App\Models\LeadQualification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Lead listing and details, shared by admins (all leads) and dispatchers
 * (only their own leads, enforced by Lead::scopeVisibleTo + LeadPolicy).
 */
class LeadController extends Controller
{
    public function index(LeadIndexRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $filters = $request->validated();

        $query = Lead::query()
            ->visibleTo($user)
            ->with(['assignee', 'qualification'])
            ->search($filters['search'] ?? null)
            ->when($filters['status'] ?? null, fn (Builder $q, $statuses) => $q->whereIn('status', $statuses))
            ->when($filters['channel'] ?? null, fn (Builder $q, $channel) => $q->where('channel', $channel))
            ->when($filters['interest_level'] ?? null, fn (Builder $q, $level) => $q->whereHas('qualification', fn ($q) => $q->where('interest_level', $level)))
            ->when($filters['next_action'] ?? null, fn (Builder $q, $action) => $q->whereHas('qualification', fn ($q) => $q->where('next_action', $action)))
            ->when($request->boolean('callback_due'), fn (Builder $q) => $q->whereNotNull('callback_at')->where('callback_at', '<=', now()->endOfDay()))
            ->when($user->isAdmin() && isset($filters['assigned_to']), fn (Builder $q) => $filters['assigned_to'] === 'none'
                ? $q->whereNull('assigned_to')
                : $q->where('assigned_to', (int) $filters['assigned_to']));

        $sort = $filters['sort'] ?? 'priority';
        $direction = $filters['direction'] ?? ($sort === 'priority' ? 'asc' : 'desc');

        if ($sort === 'priority') {
            $this->orderByImportance($query, $direction);
        } elseif (in_array($sort, ['interest_score', 'priority_stars'], true)) {
            $query->orderBy(
                LeadQualification::select($sort)->whereColumn('lead_qualifications.lead_id', 'leads.id')->limit(1),
                $direction,
            );
        } else {
            $query->orderBy($sort, $direction);
        }
        $query->orderBy('id', 'desc');

        return LeadResource::collection($query->paginate($filters['per_page'] ?? 25)->withQueryString());
    }

    /**
     * Status importance (LeadStatus::byImportance), then the soonest callback,
     * then the most recent lead. "asc" = most important first.
     */
    private function orderByImportance(Builder $query, string $direction): void
    {
        $statuses = array_map(fn (LeadStatus $s) => $s->value, LeadStatus::byImportance());
        $cases = implode(' ', array_map(fn ($i) => "WHEN ? THEN {$i}", array_keys($statuses)));

        $query
            ->orderByRaw("CASE status {$cases} ELSE 99 END {$direction}", $statuses)
            ->orderByRaw('CASE WHEN callback_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('callback_at')
            ->orderByDesc('source_created_at');
    }

    public function show(Request $request, Lead $lead): LeadResource
    {
        Gate::authorize('view', $lead);

        $lead->load([
            'assignee',
            'qualification',
            'openCall' => fn ($q) => $q->where('dispatcher_id', $request->user()->id),
        ]);

        return new LeadResource($lead);
    }

    /**
     * Calls, notes, assignments and audit events of a lead.
     */
    public function timeline(Lead $lead): array
    {
        Gate::authorize('view', $lead);

        return [
            'calls' => CallAttemptResource::collection($lead->callAttempts()->with('dispatcher')->limit(100)->get()),
            'notes' => DispatcherNoteResource::collection($lead->notes()->with('author')->limit(100)->get()),
            'assignments' => LeadAssignmentResource::collection($lead->assignments()->with(['fromUser', 'toUser', 'assigner'])->limit(50)->get()),
            'events' => AuditLogResource::collection($lead->auditLogs()->with('user')->limit(150)->get()),
        ];
    }
}
