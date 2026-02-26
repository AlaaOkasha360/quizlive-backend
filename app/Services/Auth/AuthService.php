<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Traits\MediaStorageTrait;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class AuthService
{
    use MediaStorageTrait;

    /**
     * Register a new user and send verification email.
     */
    public function register(array $data): array
    {
        $user = User::create([
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'signup_step' => 1,
        ]);

        // Fires the Registered event which sends the verification email
        event(new Registered($user));

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Handle Google OAuth callback — find or create user and issue token.
     */
    public function handleGoogleCallback(): array
    {
        $googleUser = Socialite::driver('google')->stateless()->user();

        $user = User::where('provider_name', 'google')
            ->where('provider_id', $googleUser->getId())
            ->first();

        if (!$user) {
            // Check if a user with this email already exists (registered via email)
            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                // Link Google to existing account
                $user->update([
                    'provider_name' => 'google',
                    'provider_id' => $googleUser->getId(),
                ]);
            } else {
                // Create a brand new user
                $user = User::create([
                    'full_name' => $googleUser->getName(),
                    'email' => $googleUser->getEmail(),
                    'provider_name' => 'google',
                    'provider_id' => $googleUser->getId(),
                    'email_verified_at' => now(),
                    'signup_step' => 1,
                ]);
            }
        }

        // Google users are always verified
        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user->fresh(),
            'token' => $token,
            'is_new' => !$user->isOnboarded(),
        ];
    }

    /**
     * Set the user's role (step 2).
     */
    public function selectRole(User $user, string $role): User
    {
        $user->update([
            'role' => $role,
            'signup_step' => 2,
        ]);

        return $user->fresh();
    }

    /**
     * Complete the user's profile and optionally upload a profile photo (step 3).
     */
    public function completeProfile(User $user, array $data, ?UploadedFile $photo = null): User
    {
        $updateData = [
            'display_name' => $data['display_name'],
            'username' => $data['username'],
            'country' => $data['country'],
            'bio' => $data['bio'] ?? null,
            'signup_step' => 3,
        ];

        if ($photo) {
            $updateData['profile_photo'] = $this->saveMedia($photo, 'profile_photos');
        }

        $user->update($updateData);

        return $user->fresh();
    }

    /**
     * Store the user's interests (step 4 — fully onboarded).
     */
    public function selectInterests(User $user, array $interests): User
    {
        $user->update([
            'interests' => $interests,
            'signup_step' => 4,
        ]);

        return $user->fresh();
    }

    /**
     * Authenticate a user with email and password.
     */
    public function login(array $data): array
    {
        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw new \Illuminate\Validation\ValidationException(
                validator: validator($data, []),
                response: \App\Util\ApiResponse::unauthorized('Invalid email or password.')
            );
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
            'is_new' => !$user->isOnboarded(),
        ];
    }
}
