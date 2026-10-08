<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Dispatcher data isolation. A lead that is not assigned to the dispatcher is
 * reported as "not found" so IDs cannot be probed through the API.
 */
class LeadPolicy
{
    public function view(User $user, Lead $lead): Response
    {
        return $user->isAdmin() || $this->owns($user, $lead)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Calling and qualifying is reserved to the assigned dispatcher.
     */
    public function work(User $user, Lead $lead): Response
    {
        if ($user->isDispatcher() && $this->owns($user, $lead)) {
            return Response::allow();
        }

        return $user->isAdmin()
            ? Response::deny('Seul le dispatcher assigné peut traiter ce lead.')
            : Response::denyAsNotFound();
    }

    public function addNote(User $user, Lead $lead): Response
    {
        return $user->isAdmin() || $this->owns($user, $lead)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }

    private function owns(User $user, Lead $lead): bool
    {
        return $lead->assigned_to !== null && $lead->assigned_to === $user->id;
    }
}
