<?php

namespace App\Http\Controllers\Agent\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\AgentAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(
        protected AgentAuthService $authService
    ) {}

    /**
     * Show agent login form.
     */
    public function showLoginForm(): View
    {
        return view('agent.auth.login');
    }

    /**
     * Process agent login request.
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        if ($this->authService->authenticate($credentials, $remember)) {
            return redirect()->intended(route('agent.dashboard'));
        }

        return back()
            ->withErrors(['email' => 'These credentials do not match our agent records.'])
            ->withInput($request->only('email', 'remember'));
    }

    /**
     * Log out agent.
     */
    public function logout(Request $request): RedirectResponse
    {
        $this->authService->logout();

        return redirect()->route('agent.login')
            ->with('status', 'You have been successfully logged out.');
    }
}
