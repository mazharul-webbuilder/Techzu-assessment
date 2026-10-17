<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Auth;

/**
 * Handles authentication business logic for Support Agents using the 'agent' guard.
 */
class AgentAuthService
{
    protected string $guard = 'agent';

    /**
     * Attempt agent login, regenerating session on success to prevent session fixation.
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
     * Log out agent, invalidate session, and regenerate CSRF token.
     */
    public function logout(): void
    {
        Auth::guard($this->guard)->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }
}
