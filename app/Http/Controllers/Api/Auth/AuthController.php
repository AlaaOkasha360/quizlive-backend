<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompleteProfileRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\SelectInterestsRequest;
use App\Http\Requests\Auth\SelectRoleRequest;
use App\Services\Auth\AuthService;
use App\Util\ApiResponse;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {
    }

    /**
     * Step 1: Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return ApiResponse::created('Registration successful. Please verify your email.', [
            'user' => $result['user'],
            'token' => $result['token'],
        ]);
    }

    /**
     * Step 2: Select a role (teacher/student).
     */
    public function selectRole(SelectRoleRequest $request): JsonResponse
    {
        $user = $this->authService->selectRole(
            $request->user(),
            $request->validated('role')
        );

        return ApiResponse::success('Role selected successfully.', [
            'user' => $user,
        ]);
    }

    /**
     * Step 3: Complete profile information and optionally upload a profile photo.
     */
    public function completeProfile(CompleteProfileRequest $request): JsonResponse
    {
        $user = $this->authService->completeProfile(
            $request->user(),
            $request->validated(),
            $request->file('profile_photo')
        );

        return ApiResponse::success('Profile completed successfully.', [
            'user' => $user,
        ]);
    }

    /**
     * Step 4: Select interests.
     */
    public function selectInterests(SelectInterestsRequest $request): JsonResponse
    {
        $user = $this->authService->selectInterests(
            $request->user(),
            $request->validated('interests')
        );

        return ApiResponse::success('Interests saved successfully. Onboarding complete!', [
            'user' => $user,
        ]);
    }
}
