<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * POST /api/auth/token
     *
     * Issues a Sanctum API token for the Kotlin engine.
     * Only the account whose email matches ENGINE_EMAIL in .env may use this
     * endpoint. Regular player accounts are rejected with 403.
     *
     * Route is throttled (10 req/min) — see routes/api.php.
     *
     * Request body:
     *   email     string
     *   password  string
     *
     * Response:
     *   token  string — plain-text bearer token
     */
    public function token(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // Only the designated engine account may obtain a Bearer token.
        // This prevents regular players from minting permanent API tokens.
        $engineEmail = config('app.engine_email');
        if ($engineEmail && strtolower($data['email']) !== strtolower($engineEmail)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (!Auth::attempt(['email' => $data['email'], 'password' => $data['password']])) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $token = Auth::user()->createToken('kotlin-engine')->plainTextToken;

        return response()->json(['token' => $token], 201);
    }
}
