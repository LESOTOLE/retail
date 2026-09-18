<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Auth & Profiles (PRD 6.1).
 */
class AuthController extends Controller
{
    /**
     * POST /api/v1/auth/register - Pendaftaran akun customer.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            ...$request->safe()->only(['name', 'email', 'phone']),
            'password' => $request->validated('password'),
            'role' => UserRole::Customer,
        ]);

        $user->assignRole(UserRole::Customer->value);

        $token = $user->createToken('api-token')->plainTextToken;

        return ApiResponse::created([
            'user' => new UserResource($user->load('roles')),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Registrasi berhasil');
    }

    /**
     * POST /api/v1/auth/login - Menghasilkan Sanctum Bearer Token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            return ApiResponse::error(
                'Email atau password salah.',
                ['email' => ['Kredensial tidak cocok dengan data kami.']],
                HttpResponse::HTTP_UNAUTHORIZED
            );
        }

        $deviceName = $request->validated('device_name') ?? 'api-token';
        $token = $user->createToken($deviceName)->plainTextToken;

        return ApiResponse::success([
            'user' => new UserResource($user->load('roles')),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Login berhasil');
    }

    /**
     * POST /api/v1/auth/logout - Revoke token aktif.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, 'Logout berhasil, token telah dicabut');
    }

    /**
     * GET /api/v1/auth/me - Profil user aktif.
     */
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(
            new UserResource($request->user()->load('roles')),
            'Profil user berhasil diambil'
        );
    }
}
