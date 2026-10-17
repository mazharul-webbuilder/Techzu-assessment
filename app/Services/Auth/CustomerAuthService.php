<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Auth;

/**
 * Handles authentication business logic for Customer accounts using the 'customer' guard.
 */
class CustomerAuthService
{
    protected string $guard = 'customer';

    /**
     * Attempt customer login, regenerating session on success to prevent session fixation.
     */
    public function authenticate(array $credentials, bool $remember = false): bool
    {
        if (Auth::guard($this->guard)->attempt($credentials, $remember)) {
            request()->session()->regenerate();
            return true;
        }

        return false;
    }

    /**
     * Log out customer, invalidate session, and regenerate CSRF token.
     */
    public function logout(): void
    {
        Auth::guard($this->guard)->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }
}
