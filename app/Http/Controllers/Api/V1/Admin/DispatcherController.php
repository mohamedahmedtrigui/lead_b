<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Services\LeadAssignmentService;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Users\Services\DispatcherService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AllocateLeadsRequest;
use App\Http\Requests\Admin\StoreDispatcherRequest;
use App\Http\Requests\Admin\UpdateDispatcherRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DispatcherController extends Controller
{
    public function __construct(
        private readonly DispatcherService $dispatchers,
        private readonly LeadAssignmentService $assignments,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(UserStatus::class)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $users = User::query()
            ->dispatchers()
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('search'), function ($q, $search) {
                $like = '%'.$search.'%';
                $q->where(fn ($q) => $q->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like));
            })
            ->withCount([
                'assignedLeads as assigned_leads_count',
                'assignedLeads as open_leads_count' => fn ($q) => $q->whereIn('status', LeadStatus::open()),
            ])
            ->orderByRaw("CASE WHEN status = 'PENDING' THEN 0 ELSE 1 END")
            ->orderBy('first_name')
            ->get();

        return UserResource::collection($users);
    }

    /**
     * Creates an approved dispatcher and optionally gives it N untouched leads
     * straight away (all or nothing, in one transaction).
     */
    public function store(StoreDispatcherRequest $request): JsonResponse
    {
        $data = $request->validated();

        [$user, $allocation] = DB::transaction(function () use ($data, $request) {
            $user = $this->dispatchers->create($data, $request->user());

            return [$user, $this->allocateInitialLeads($user, $data, $request)];
        });

        return response()->json([...(new UserResource($user))->resolve($request), 'allocation' => $allocation], 201);
    }

    public function update(UpdateDispatcherRequest $request, User $user): UserResource
    {
        abort_unless($user->isDispatcher(), 404);

        return new UserResource($this->dispatchers->update($user, $request->validated()));
    }

    public function approve(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(AllocateLeadsRequest::fields());

        [$user, $allocation] = DB::transaction(function () use ($user, $data, $request) {
            $user = $this->dispatchers->approve($user, $request->user());

            return [$user, $this->allocateInitialLeads($user, $data, $request)];
        });

        return response()->json([...(new UserResource($user))->resolve($request), 'allocation' => $allocation]);
    }

    /**
     * Safe deletion (archive): see DispatcherService::delete().
     */
    public function destroy(Request $request, User $user): array
    {
        $data = $request->validate([
            'open_leads' => ['required', Rule::in([DispatcherService::OPEN_LEADS_REDISTRIBUTE, DispatcherService::OPEN_LEADS_RELEASE])],
            'transfer_processed_to' => ['nullable', 'integer', 'exists:users,id'],
            'confirmation' => ['required', 'in:SUPPRIMER'],
        ], ['confirmation.in' => 'Tapez SUPPRIMER pour confirmer.']);

        $target = isset($data['transfer_processed_to']) ? User::find($data['transfer_processed_to']) : null;

        return $this->dispatchers->delete($user, $request->user(), $data['open_leads'], $target);
    }

    /**
     * Allocates N untouched leads to an active dispatcher, at any time.
     */
    public function allocate(AllocateLeadsRequest $request, User $user): array
    {
        return $this->allocateInitialLeads($user, $request->validated(), $request);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, int>|null
     */
    private function allocateInitialLeads(User $user, array $data, Request $request): ?array
    {
        $count = (int) ($data['initial_leads'] ?? 0);
        if ($count < 1) {
            return null;
        }

        return $this->assignments->allocate($user, $count, $request->user(), (bool) ($data['allow_rebalance'] ?? true));
    }

    public function reject(Request $request, User $user): UserResource
    {
        return new UserResource($this->dispatchers->reject($user, $request->user()));
    }

    public function deactivate(Request $request, User $user): UserResource
    {
        $request->validate(['release_leads' => ['sometimes', 'boolean']]);

        return new UserResource($this->dispatchers->deactivate($user, $request->user(), $request->boolean('release_leads', true)));
    }

    public function reactivate(Request $request, User $user): UserResource
    {
        return new UserResource($this->dispatchers->reactivate($user, $request->user()));
    }
}
