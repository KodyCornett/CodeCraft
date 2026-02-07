<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Security\SentinelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SentinelController extends Controller
{
    public function __construct(
        private readonly SentinelService $sentinelService,
    ) {}

    /**
     * Get current security status.
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'status' => $this->sentinelService->getStatus(),
            'connections' => $this->sentinelService->getIncomingConnections(),
            'threats' => $this->sentinelService->getActiveThreats(),
        ]);
    }

    /**
     * Get security event log.
     */
    public function events(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'events' => $this->sentinelService->getEventLog(),
        ]);
    }

    /**
     * Block an incoming connection.
     */
    public function block(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'connectionId' => 'required|string',
        ]);

        $result = $this->sentinelService->blockConnection($validated['connectionId']);

        return response()->json($result);
    }

    /**
     * Trace an incoming connection to reveal attacker info.
     */
    public function trace(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'connectionId' => 'required|string',
        ]);

        $result = $this->sentinelService->traceConnection($validated['connectionId']);

        return response()->json($result);
    }

    /**
     * Attempt counter-hack against attacker.
     */
    public function counterHack(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'connectionId' => 'required|string',
        ]);

        $result = $this->sentinelService->counterHack($validated['connectionId']);

        return response()->json($result);
    }
}
