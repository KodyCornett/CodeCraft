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

    /**
     * POST /api/files
     *
     * Creates a new file inside one of the player's own folders.
     * Body: { "parent_id": "uuid", "name": "string", "extension": "string?" }
     */
    public function store(Request $request): JsonResponse
    {
        $player = Player::where('user_id', $request->user()->id)->first();
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        $data = $request->validate([
            'parent_id' => 'required|uuid',
            'name'      => 'required|string|max:100',
            'extension' => 'nullable|string|max:20',
        ]);

        $file = $this->fileService->createFile($player, $data['parent_id'], $data['name'], $data['extension'] ?? null);
        if ($file === null) {
            return response()->json(['message' => 'Folder not found.'], 404);
        }

        return response()->json($file, 201);
    }

    /**
     * DELETE /api/files/{fileId}
     *
     * Deletes a file the player owns. Refuses (403) protected files and
     * folders — the seeded structure can't be removed this way.
     */
    public function destroy(Request $request, string $fileId): JsonResponse
    {
        $player = Player::where('user_id', $request->user()->id)->first();
        if ($player === null) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        $deleted = $this->fileService->deleteFile($player, $fileId);
        if (!$deleted) {
            return response()->json(['message' => 'File not found or cannot be deleted.'], 403);
        }

        return response()->json(['ok' => true]);
    }
}
