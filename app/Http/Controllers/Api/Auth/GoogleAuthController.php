<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthService;
use App\Util\ApiResponse;
use Illuminate\Http\JsonResponse;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {
    }

    /**
     * Redirect the user to Google's OAuth consent screen.
     */
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the callback from Google after authentication.
     */
    public function callback(): JsonResponse
    {
        try {
            $result = $this->authService->handleGoogleCallback();

            return ApiResponse::success('Google authentication successful.', [
                'user' => $result['user'],
                'token' => $result['token'],
                'is_new' => $result['is_new'],
            ]);
        } catch (\Exception $e) {
            return ApiResponse::error('Google authentication failed: ' . $e->getMessage());
        }
    }
}
