<?php

namespace App\Http\Controllers;

use App\Constants\Personas;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PersonaController extends Controller
{
    /**
     * POST /api/player/persona
     *
     * Sets the player's persona on first login.
     * Permanent — once set this cannot be changed.
     *
     * Body: { "persona": "Ghost" }
     */
    public function store(Request $request): JsonResponse
    {
        $player = Player::where('user_id', $request->user()->id)->first();
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        // Already set — permanent choice, cannot be changed
        if ($player->persona !== null) {
            return response()->json(['message' => 'Persona already set. This choice is permanent.'], 422);
        }

        $data = $request->validate([
            'persona' => ['required', 'string', Rule::in(Personas::names())],
        ]);

        $player->persona      = $data['persona'];
        $player->persona_desc = Personas::descFor($data['persona']);
        $player->save();

        return response()->json([
            'persona'      => $player->persona,
            'persona_desc' => $player->persona_desc,
        ]);
    }
}
