<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks accounts that are pending, rejected or deactivated, even if they
 * still hold a valid session (e.g. deactivated while logged in).
 */
class EnsureUserIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isApproved()) {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return response()->json([
                'message' => 'Votre compte n\'est pas actif.',
                'code' => 'account_'.strtolower($user->status->value),
            ], 403);
        }

        return $next($request);
    }
}
