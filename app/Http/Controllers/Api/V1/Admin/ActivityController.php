<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Audit\AuditEvent;
use App\Domain\Calls\Enums\CallOutcome;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\CallAttemptResource;
use App\Models\AuditLog;
use App\Models\CallAttempt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Call history and audit trail across all dispatchers.
 */
class ActivityController extends Controller
{
    public function calls(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'dispatcher_id' => ['nullable', 'integer'],
            'outcome' => ['nullable', Rule::enum(CallOutcome::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $calls = CallAttempt::query()
            ->with(['lead', 'dispatcher'])
            ->when($filters['dispatcher_id'] ?? null, fn ($q, $id) => $q->where('dispatcher_id', $id))
            ->when($filters['outcome'] ?? null, fn ($q, $o) => $q->where('outcome', $o))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('started_at', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('started_at', '<=', $d.' 23:59:59'))
            ->latest('started_at')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();

        return CallAttemptResource::collection($calls);
    }

    public function audit(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'event' => ['nullable', Rule::enum(AuditEvent::class)],
            'user_id' => ['nullable', 'integer'],
            'lead_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $logs = AuditLog::query()
            ->with(['user', 'lead'])
            ->when($filters['event'] ?? null, fn ($q, $e) => $q->where('event', $e))
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($filters['lead_id'] ?? null, fn ($q, $id) => $q->where('lead_id', $id))
            ->latest('created_at')
            ->latest('id')
            ->paginate($filters['per_page'] ?? 30)
            ->withQueryString();

        return AuditLogResource::collection($logs);
    }
}
