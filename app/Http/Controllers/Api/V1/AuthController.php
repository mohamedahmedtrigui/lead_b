<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Users\Enums\UserStatus;
use App\Domain\Users\Services\DispatcherService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, DispatcherService $dispatchers): JsonResponse
    {
        $dispatchers->register($request->validated());

        return response()->json([
            'message' => 'Inscription enregistrée. Votre compte doit être validé par un administrateur avant de pouvoir vous connecter.',
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse|UserResource
    {
        $credentials = $request->only('email', 'password');
        $credentials['email'] = strtolower($credentials['email']);

        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'Adresse e-mail ou mot de passe incorrect.']);
        }

        $user = Auth::guard('web')->user();

        if (! $user->isApproved()) {
            Auth::guard('web')->logout();

            return response()->json([
                'message' => match ($user->status) {
                    UserStatus::PENDING => 'Votre inscription est en attente de validation par un administrateur.',
                    UserStatus::REJECTED => 'Votre inscription a été refusée.',
                    default => 'Votre compte a été désactivé. Contactez un administrateur.',
                },
                'code' => 'account_'.strtolower($user->status->value),
            ], 403);
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return new UserResource($user);
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
