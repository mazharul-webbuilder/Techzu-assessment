<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\CustomerAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(
        protected CustomerAuthService $authService
    ) {}

    /**
     * Show customer login form.
     */
    public function showLoginForm(): View
    {
        return view('customer.auth.login');
    }

    /**
     * Process customer login request.
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        if ($this->authService->authenticate($credentials, $remember)) {
            return redirect()->intended(route('customer.dashboard'));
        }

        return back()
            ->withErrors(['email' => 'These credentials do not match our customer records.'])
            ->withInput($request->only('email', 'remember'));
    }

    /**
     * Log out customer.
     */
    public function logout(Request $request): RedirectResponse
    {
        $this->authService->logout();

        return redirect()->route('customer.login')
            ->with('status', 'You have been successfully logged out.');
    }
}
