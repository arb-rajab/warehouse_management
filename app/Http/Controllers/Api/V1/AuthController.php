<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\FailsAuthenticationUniformly;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    use FailsAuthenticationUniformly;

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->findUserOrFailUniformly($request->input('email'), $request->input('password'));

        $token = $user->createToken('mobile');

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $user->currentAccessToken()->delete();

        return response()->noContent();
    }
}
