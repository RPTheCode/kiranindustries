<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\EmployeeProfileResource;
use App\Http\Resources\Mobile\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = mobileUserEmployee($user);

        if ($employee) {
            $employee->loadMissing([
                'branch',
                'department',
                'designation',
                'shift.slots',
                'category',
            ]);
        }

        return response()->json([
            'user' => new UserResource($user),
            'employee' => $employee ? new EmployeeProfileResource($employee) : null,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'avatar' => ['sometimes', 'nullable', 'image', 'max:2048'],
            'profile_image' => ['sometimes', 'nullable', 'image', 'max:2048'],
        ]);

        $avatarFile = $request->file('avatar') ?? $request->file('profile_image');

        if ($avatarFile) {
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $validated['avatar'] = $avatarFile->store('avatars', 'public');
        }

        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }

        if (array_key_exists('email', $validated)) {
            $user->email = $validated['email'];
        }

        if (array_key_exists('avatar', $validated)) {
            $user->avatar = $validated['avatar'];
        }

        $user->save();

        $employee = mobileUserEmployee($user);

        if (isset($validated['phone']) && $employee) {
            $employee->update(['phone' => $validated['phone']]);
        }

        if ($employee) {
            $employee->loadMissing([
                'branch',
                'department',
                'designation',
                'shift.slots',
                'category',
            ]);
        }

        return response()->json([
            'message' => __('Profile updated successfully.'),
            'user' => new UserResource($user),
            'employee' => $employee ? new EmployeeProfileResource($employee) : null,
        ]);
    }
}
