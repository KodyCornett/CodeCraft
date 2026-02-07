<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FilesystemController extends Controller
{
    /**
     * Mock filesystem structure.
     * In a real implementation, this would come from the database.
     */
    private array $filesystem = [
        '/home/user' => [
            ['name' => 'documents', 'type' => 'directory'],
            ['name' => 'downloads', 'type' => 'directory'],
            ['name' => '.config', 'type' => 'directory'],
            ['name' => 'notes.txt', 'type' => 'file'],
            ['name' => 'readme.txt', 'type' => 'file'],
        ],
        '/home/user/documents' => [
            ['name' => 'mission_briefing.txt', 'type' => 'file'],
            ['name' => 'contacts.txt', 'type' => 'file'],
            ['name' => 'network_map.bin', 'type' => 'file'],
        ],
        '/home/user/downloads' => [
            ['name' => 'exploit_v2.bin', 'type' => 'file'],
            ['name' => 'readme.txt', 'type' => 'file'],
        ],
        '/home/user/.config' => [
            ['name' => 'settings.txt', 'type' => 'file'],
        ],
    ];

    private array $fileContents = [
        '/home/user/notes.txt' => "Remember: The password hint is in the server logs.\nDon't trust Marcus.\n\nTODO:\n- Check legacy-auth vulnerability\n- Contact Ghost for intel",
        '/home/user/readme.txt' => "Welcome to CodeCraft.\n\nType 'help' in the terminal to see available commands.\n\nThis system is for authorized personnel only.",
        '/home/user/documents/mission_briefing.txt' => "=== MISSION BRIEFING ===\n\nTARGET: Meridian Corp\nOBJECTIVE: Extract personnel database\n\nINTEL:\n- Their legacy-auth system has known vulnerabilities\n- Access point: 192.168.50.1\n- Estimated security level: Medium\n\nCONTACT: Ghost (encrypted channel)\nPAYMENT: Upon completion\n\nWARNING: Trace detection is active. Work quickly.",
        '/home/user/documents/contacts.txt' => "=== TRUSTED CONTACTS ===\n\nGHOST\n- Reliable. Expensive.\n- Specializes in intel gathering\n- Response time: 2-4 hours\n\nZERO\n- New. Unverified.\n- Claims expertise in network infiltration\n- Use with caution\n\n=== AVOID ===\n\nMARCUS\n- Suspected fed\n- Last seen asking about Meridian job\n- DO NOT ENGAGE",
        '/home/user/documents/network_map.bin' => "[BINARY DATA]\n\n00101010 01001101 01000101 01010010\n01001001 01000100 01001001 01000001\n01001110 00100000 01001110 01000101\n01010100 01010111 01001111 01010010\n\n[CORRUPTED - PARTIAL DATA RECOVERED]\n\nNode: 192.168.50.1 (gateway)\nNode: 192.168.50.10 (auth-server)\nNode: 192.168.50.25 (database)\n\n[END OF RECOVERED DATA]",
        '/home/user/downloads/exploit_v2.bin' => "[EXECUTABLE - v2.0.1]\n\nUSAGE: exploit <target> --port <port>\n\nKNOWN COMPATIBLE SYSTEMS:\n- legacy-auth v1.x\n- legacy-auth v2.0-2.3\n\nWARNING: Use increases trace level significantly.\nRecommended to have exit strategy prepared.",
        '/home/user/downloads/readme.txt' => "Downloaded from: darknet://tools.onion\nDate: [REDACTED]\n\nThis archive contains tools for security research.\nUse responsibly. You are responsible for all actions.",
        '/home/user/.config/settings.txt' => "# System Configuration\n\nterminal.font_size=12\nterminal.theme=dark\nnetwork.proxy=auto\ntrace.alert_threshold=0.7\n\n# Do not modify below this line\nuser.clearance=3\nuser.id=xK7mN2pQ",
    ];

    public function handle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|in:list,read',
            'path' => 'required|string|max:500',
        ]);

        return match ($validated['action']) {
            'list' => $this->listDirectory($validated['path']),
            'read' => $this->readFile($validated['path']),
            default => response()->json(['success' => false, 'error' => 'Unknown action']),
        };
    }

    private function listDirectory(string $path): JsonResponse
    {
        // Normalize path
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        $files = $this->filesystem[$path] ?? null;

        if ($files === null) {
            return response()->json([
                'success' => false,
                'error' => 'Directory not found',
            ]);
        }

        return response()->json([
            'success' => true,
            'path' => $path,
            'files' => $files,
        ]);
    }

    private function readFile(string $path): JsonResponse
    {
        $content = $this->fileContents[$path] ?? null;

        if ($content === null) {
            return response()->json([
                'success' => false,
                'error' => 'File not found',
            ]);
        }

        return response()->json([
            'success' => true,
            'path' => $path,
            'content' => $content,
        ]);
    }
}
