<?php

namespace App\Http\Controllers;

/**
 * GridBreach PvP combat resolution — REMOVED.
 *
 * All routes that pointed here (POST /api/combat/result,
 * GET /api/combat/result/{id}) have been removed from api.php.
 * PvP combat is now resolved exclusively via PacketHijackController.
 * The challenge handshake lives in CombatChallengeController.
 */
class CombatController extends Controller
{
}
