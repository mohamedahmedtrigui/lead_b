<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Users\Services\DispatcherService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDispatcherRequest;
use App\Http\Requests\Admin\UpdateDispatcherRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class DispatcherController extends Controller
{
    public function __construct(private readonly DispatcherService $dispatchers) {}

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

    public function store(StoreDispatcherRequest $request): JsonResponse
    {
        $user = $this->dispatchers->create($request->validated(), $request->user());

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function update(UpdateDispatcherRequest $request, User $user): UserResource
    {
        abort_unless($user->isDispatcher(), 404);

        return new UserResource($this->dispatchers->update($user, $request->validated()));
    }

    public function approve(Request $request, User $user): UserResource
    {
        return new UserResource($this->dispatchers->approve($user, $request->user()));
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
