<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Qualification\Services\QualificationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Qualification\CompleteQualificationRequest;
use App\Http\Requests\Qualification\SaveQualificationRequest;
use App\Http\Resources\LeadQualificationResource;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class QualificationController extends Controller
{
    public function __construct(private readonly QualificationService $qualifications) {}

    public function show(Lead $lead): JsonResponse|LeadQualificationResource
    {
        Gate::authorize('view', $lead);

        $qualification = $lead->qualification()->first();

        return $qualification ? new LeadQualificationResource($qualification) : response()->json(null);
    }

    /**
     * Draft save, called by the wizard on each step change.
     */
    public function update(SaveQualificationRequest $request, Lead $lead): JsonResponse
    {
        $qualification = $this->qualifications->saveDraft($lead, $request->user(), $request->validated());

        // Upsert: always 200, even when the draft was just created.
        return (new LeadQualificationResource($qualification))->response()->setStatusCode(200);
    }

    public function complete(CompleteQualificationRequest $request, Lead $lead): array
    {
        $qualification = $this->qualifications->complete($lead, $request->user(), $request->validated());

        return [
            'qualification' => new LeadQualificationResource($qualification),
            'lead' => new LeadResource($lead->fresh(['assignee', 'qualification'])),
        ];
    }
}
