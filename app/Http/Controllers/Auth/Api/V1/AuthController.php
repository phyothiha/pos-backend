<?php

namespace App\Http\Controllers\Auth\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\Api\V1\LoginRequest;
use App\Http\Resources\Auth\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $token = Auth::guard('api')->attempt($request->validated());

        if (! $token) {
            return response()->json(['message' => 'Invalid username or password.'], 401);
        }

        return $this->respondWithToken($token);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user('api'));
    }

    public function refresh(): JsonResponse
    {
        return $this->respondWithToken(Auth::guard('api')->refresh());
    }

    public function logout(): JsonResponse
    {
        Auth::guard('api')->logout();

        return response()->json(['message' => 'Successfully logged out.']);
    }

    private function respondWithToken(string $token): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
        ]);
    }
}
