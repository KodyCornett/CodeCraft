<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    /**
     * GET /login — render the Inertia login page.
     */
    public function show(): Response
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * POST /login — authenticate and redirect into the game.
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'       => ['required', 'email'],
            'password'    => ['required', 'string'],
            'remember_me' => ['boolean'],
        ]);

        $remember = (bool) ($credentials['remember_me'] ?? false);
        unset($credentials['remember_me']);

        if (!Auth::attempt($credentials, remember: $remember)) {
            return back()->withErrors([
                'email' => 'Invalid credentials — check your handle and access code.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    /**
     * POST /logout — end the session and return to login.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
