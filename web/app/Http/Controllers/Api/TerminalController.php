<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\GameEngine\GameEngineInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TerminalController extends Controller
{
    public function __construct(
        private readonly GameEngineInterface $engine,
    ) {}

    public function execute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'command' => 'required|string|max:1000',
            'context' => 'array',
            'context.currentPath' => 'string',
        ]);

        $sessionId = session()->getId();
        $command = $validated['command'];
        $context = $validated['context'] ?? [];

        $result = $this->engine->executeCommand($sessionId, $command, $context);

        return response()->json([
            'success' => $result->success,
            'output' => $result->output,
            'lines' => $result->lines,
            'delayMs' => $result->delayMs,
            'traceIncrease' => $result->traceIncrease,
            'stateChanges' => $result->stateChanges,
            'errorType' => $result->errorType,
        ]);
    }
}
