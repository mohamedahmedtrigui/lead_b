<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Calls\Enums\CallOutcome;
use App\Domain\Calls\Services\CallService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\CallOutcomeRequest;
use App\Http\Resources\CallAttemptResource;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class CallController extends Controller
{
    public function __construct(private readonly CallService $calls) {}

    public function start(Request $request, Lead $lead): array
    {
        Gate::authorize('work', $lead);

        $call = $this->calls->start($lead, $request->user());

        return [
            'call' => new CallAttemptResource($call),
            'lead' => new LeadResource($lead->fresh(['assignee', 'qualification'])),
        ];
    }

    /**
     * Registers the outcome of the current call: NRP, callback, invalid number,
     * not interested, or simply ends a connected call.
     */
    public function outcome(CallOutcomeRequest $request, Lead $lead): LeadResource
    {
        $data = $request->validated();

        $this->calls->registerOutcome($lead, $request->user(), CallOutcome::from($data['outcome']), [
            'note' => $data['note'] ?? null,
            'callback_at' => isset($data['callback_at']) ? Carbon::parse($data['callback_at']) : null,
        ]);

        return new LeadResource($lead->fresh(['assignee', 'qualification']));
    }
}
