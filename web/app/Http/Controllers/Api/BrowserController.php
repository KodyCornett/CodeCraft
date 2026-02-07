<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Browser\SiteRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrowserController extends Controller
{
    public function __construct(
        private readonly SiteRegistry $siteRegistry,
    ) {}

    /**
     * Get list of available sites (bookmarks).
     */
    public function bookmarks(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'bookmarks' => $this->siteRegistry->getBookmarks(),
        ]);
    }

    /**
     * Navigate to a URL and get page content.
     */
    public function navigate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => 'required|string|max:500',
        ]);

        $url = $validated['url'];

        // Parse the URL (format: domain/path or matrix://domain/path)
        $url = preg_replace('/^matrix:\/\//', '', $url);
        $parts = explode('/', $url, 2);
        $domain = $parts[0];
        $path = '/' . ($parts[1] ?? '');

        // Check if site exists
        $site = $this->siteRegistry->get($domain);

        if (!$site) {
            return response()->json([
                'success' => false,
                'error' => 'dns_error',
                'message' => "Could not resolve host: {$domain}",
            ]);
        }

        // Get player context (in real game, from session/database)
        $context = $this->getPlayerContext();

        // Render the page
        $page = $site->render($path, $context);
        $navigation = $site->getNavigation($context);

        return response()->json([
            'success' => true,
            'domain' => $domain,
            'path' => $path,
            'siteName' => $site->getName(),
            'siteIcon' => $site->getIcon(),
            'title' => $page['title'],
            'content' => $page['content'],
            'navigation' => $navigation,
        ]);
    }

    /**
     * Get player context for rendering pages.
     * In a real implementation, this comes from the database.
     */
    private function getPlayerContext(): array
    {
        // Mock player data - would come from session/database
        return [
            'credits' => 5000,
            'unlockedCommands' => ['help', 'ls', 'cd', 'cat', 'clear', 'scan', 'connect', 'disconnect'],
            'ownedCommands' => ['help', 'ls', 'cd', 'cat', 'clear', 'scan', 'connect', 'disconnect'],
            'ownedExploits' => [],
            'ownedTools' => [],
            'ownedUpgrades' => [],
        ];
    }
}
