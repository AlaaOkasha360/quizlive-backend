<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AuthService;
use App\Util\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
    public function callback(): RedirectResponse
    {
        try {
            $result = $this->authService->handleGoogleCallback();

            $token = $result['token'];
            $isNew = $result['is_new'];

            $redirectUrl = $isNew
                ? config('app.frontend_url') . '/onboarding?token=' . $token
                : config('app.frontend_url') . '/dashboard?token=' . $token;

            return redirect($redirectUrl);
        } catch (\Exception $e) {
            return redirect(config('app.frontend_url') . '/login?error=' . urlencode($e->getMessage()));
        }
    }
}
