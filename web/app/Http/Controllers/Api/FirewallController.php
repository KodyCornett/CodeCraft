<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\GameEngine\GameEngineInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class FirewallController extends Controller
{
    public function __construct(
        private readonly GameEngineInterface $engine
    ) {}

    public function status(): JsonResponse
    {
        $sessionId = session()->getId();
        $firewallData = $this->engine->getFirewallStatus($sessionId);

        if (empty($firewallData)) {
            return response()->json([
                'success' => false,
                'error' => 'Could not retrieve firewall status',
            ]);
        }

        return response()->json([
            'success' => true,
            'status' => $firewallData['status'] ?? 'unknown',
            'traceCount' => $firewallData['traceCount'] ?? 0,
            'isOnLocalhost' => $firewallData['isOnLocalhost'] ?? true,
            'defragActive' => $firewallData['defragActive'] ?? false,
            'firewallCurrent' => $firewallData['firewallCurrent'] ?? 0,
            'firewallMax' => $firewallData['firewallMax'] ?? 100,
            'damageState' => $firewallData['damageState'] ?? 'GOOD',
        ]);
    }

    public function repair(): JsonResponse
    {
        $sessionId = session()->getId();

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(5)
                ->post(config('game.engine_url') . "/api/firewall/repair/{$sessionId}");

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'success' => false,
                'error' => 'Repair request failed',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Could not connect to game engine',
            ]);
        }
    }

    public function activate(): JsonResponse
    {
        // Activation is now handled by the Kotlin engine
        // This endpoint can remain for backward compatibility but does nothing
        return response()->json([
            'success' => true,
            'message' => 'Firewall activation handled by engine',
        ]);
    }
}
