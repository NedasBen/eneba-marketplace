<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Sanctum personal access tokens (Bearer), see ARCHITECTURE.md
 * "Authentication & token storage". Each token carries its own expires_at.
 */
class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::query()->create($request->validated());

        return $this->tokenResponse($user, 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        return $this->tokenResponse($request->authenticate(), 200);
    }

    /**
     * Revokes only the token used for this request; other devices stay signed in.
     */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => UserResource::make($request->user())->resolve()]);
    }

    private function tokenResponse(User $user, int $status): JsonResponse
    {
        $expiresAt = now()->addMinutes((int) config('auth.api_tokens.ttl_minutes'));
        $token = $user->createToken('spa', ['*'], $expiresAt);

        return response()->json([
            'user' => UserResource::make($user)->resolve(),
            'token' => $token->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
        ], $status);
    }
}
