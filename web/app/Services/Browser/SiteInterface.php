<?php

declare(strict_types=1);

namespace App\Services\Browser;

/**
 * Interface for browser sites.
 * Each site implements this to be accessible via the in-game browser.
 */
interface SiteInterface
{
    /**
     * Get the site's domain (e.g., "wiki.matrix").
     */
    public function getDomain(): string;

    /**
     * Get the site's display name.
     */
    public function getName(): string;

    /**
     * Get the site's favicon/icon (SVG or emoji).
     */
    public function getIcon(): string;

    /**
     * Get the site's description.
     */
    public function getDescription(): string;

    /**
     * Render a page on this site.
     *
     * @param string $path The path within the site (e.g., "/commands/scan")
     * @param array $context Player context (unlocked commands, credits, etc.)
     * @return array{title: string, content: string, links: array}
     */
    public function render(string $path, array $context = []): array;

    /**
     * Get the site's navigation/menu items.
     *
     * @param array $context Player context
     * @return array<array{label: string, path: string, icon?: string}>
     */
    public function getNavigation(array $context = []): array;
}
