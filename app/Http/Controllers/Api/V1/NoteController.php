<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Calls\Services\CallService;
use App\Domain\Leads\Services\NoteService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\StoreNoteRequest;
use App\Http\Resources\DispatcherNoteResource;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class NoteController extends Controller
{
    public function index(Lead $lead): AnonymousResourceCollection
    {
        Gate::authorize('view', $lead);

        return DispatcherNoteResource::collection($lead->notes()->with('author')->get());
    }

    public function store(StoreNoteRequest $request, Lead $lead, NoteService $notes, CallService $calls): JsonResponse
    {
        $user = $request->user();
        $note = $notes->add($lead, $user, $request->validated('body'), call: $calls->openCall($lead, $user));

        return (new DispatcherNoteResource($note->load('author')))->response()->setStatusCode(201);
    }
}
