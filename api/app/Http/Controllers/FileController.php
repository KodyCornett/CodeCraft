<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Services\FileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FileController extends Controller
{
    public function __construct(
        private readonly FileService $fileService,
    ) {}

    /**
     * GET /api/files
     *
     * Returns the authenticated player's whole file tree as a flat list
     * (folders and files together) for the File Explorer OS program.
     */
    public function index(Request $request): JsonResponse
    {
        $player = Player::where('user_id', $request->user()->id)->first();
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        return response()->json(['files' => $this->fileService->getTree($player)]);
    }

    /**
     * GET /api/files/{fileId}
     *
     * Returns one file's content. 404 if it doesn't exist, isn't owned by
     * this player, or is a folder.
     */
    public function show(Request $request, string $fileId): JsonResponse
    {
        $player = Player::where('user_id', $request->user()->id)->first();
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        $file = $this->fileService->getFileContent($player, $fileId);
        if ($file === null) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        return response()->json($file);
    }
}
