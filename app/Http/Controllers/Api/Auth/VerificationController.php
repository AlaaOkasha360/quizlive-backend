<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Util\ApiResponse;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    private const ONBOARDING_URL = config('app.frontend_url') . '/onboarding';

    /**
     * Mark the user's email as verified and redirect to frontend.
     */
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::find($id);

        if (!$user) {
            return redirect(self::ONBOARDING_URL . '?status=invalid');
        }

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return redirect(self::ONBOARDING_URL . '?status=invalid');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect(self::ONBOARDING_URL . '?status=already_verified');
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return redirect(self::ONBOARDING_URL . '?status=verified');
    }

    /**
     * Resend the email verification notification.
     */
    public function resend(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return ApiResponse::success('Email already verified.');
        }

        $request->user()->sendEmailVerificationNotification();

        return ApiResponse::success('Verification link sent.');
    }
}
