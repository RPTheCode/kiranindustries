<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\MobileDevice;
use App\Services\Mobile\MobileAuthPayloadBuilder;
use App\Services\Mobile\MobileLoginResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private MobileAuthPayloadBuilder $payloadBuilder,
        private MobileLoginResolver $loginResolver
    ) {}

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required_without:email', 'nullable', 'string', 'max:255'],
            'email' => ['required_without:login', 'nullable', 'email'],
            'password' => ['required', 'string'],
            'device_id' => ['required', 'string', 'max:255'],
            'fcm_token' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $identifier = trim((string) ($validated['login'] ?? $validated['email'] ?? ''));
        $user = $this->loginResolver->authenticate($identifier, $validated['password']);

        if (! $user) {
            throw ValidationException::withMessages([
                'login' => [__('These credentials do not match our records.')],
            ]);
        }

        if ((int) $user->is_enable_login !== 1 || $user->status !== 'active') {
            return response()->json([
                'message' => __('Your account is not enabled for login.'),
            ], 403);
        }

        if (! userCanAccessMobileApp($user)) {
            return response()->json([
                'message' => __('Mobile app access is not enabled for your account.'),
            ], 403);
        }

        MobileDevice::updateOrCreate(
            [
                'user_id' => $user->id,
                'device_id' => $validated['device_id'],
            ],
            [
                'fcm_token' => $validated['fcm_token'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'last_login_at' => now(),
            ]
        );

        $token = $user->createToken($validated['device_id'])->plainTextToken;

        return response()->json([
            'message' => __('Logged in successfully.'),
            'token' => $token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->payloadBuilder->build($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => __('Logged out successfully.')]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json(['message' => __('Password updated successfully.')]);
    }
}
