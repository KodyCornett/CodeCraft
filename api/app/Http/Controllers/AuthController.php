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
     * Issues a Sanctum API token for the authenticated user.
     * The Kotlin engine calls this once on startup and stores the token
     * for all subsequent authenticated requests.
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

        if (!Auth::attempt(['email' => $data['email'], 'password' => $data['password']])) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $token = Auth::user()->createToken('kotlin-engine')->plainTextToken;

        return response()->json(['token' => $token], 201);
    }
}
