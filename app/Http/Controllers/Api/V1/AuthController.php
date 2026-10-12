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

    /**
     * What a mobile token may do: every /api/v1 route, and nothing a future
     * token of another kind (an integration, a one-off export) would carry.
     * No route checks abilities yet, so this names the scope instead of `*`.
     *
     * @var list<string>
     */
    public const MOBILE_ABILITIES = ['warehouse:use'];

    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->findUserOrFailUniformly($request->input('email'), $request->input('password'));

        $token = $user->createToken('mobile', self::MOBILE_ABILITIES);

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => new UserResource($user->loadMissing('roles:id,name')),
        ]);
    }

    public function logout(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $user->currentAccessToken()->delete();

        return response()->noContent();
    }

    /**
     * The signed-in user, in the same shape as /login's `user` — lets the app
     * refresh `is_admin` on every launch instead of only at login, since a
     * server-side role change otherwise doesn't reach a session already logged in.
     */
    public function show(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($user->loadMissing('roles:id,name'));
    }
}
