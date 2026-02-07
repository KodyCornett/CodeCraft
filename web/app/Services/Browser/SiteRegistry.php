<?php

declare(strict_types=1);

namespace App\Services\Browser;

use App\Services\Browser\Sites\WikiSite;
use App\Services\Browser\Sites\StoreSite;
use App\Services\Browser\Sites\NewsSite;
use App\Services\Browser\Sites\ForumSite;

/**
 * Registry of all available browser sites.
 * Add new sites here to make them accessible.
 */
class SiteRegistry
{
    /** @var array<string, SiteInterface> */
    private array $sites = [];

    public function __construct()
    {
        $this->registerDefaultSites();
    }

    /**
     * Register the default set of sites.
     */
    private function registerDefaultSites(): void
    {
        $this->register(new WikiSite());
        $this->register(new StoreSite());
        $this->register(new NewsSite());
        $this->register(new ForumSite());
    }

    /**
     * Register a site.
     */
    public function register(SiteInterface $site): void
    {
        $this->sites[$site->getDomain()] = $site;
    }

    /**
     * Get a site by domain.
     */
    public function get(string $domain): ?SiteInterface
    {
        return $this->sites[$domain] ?? null;
    }

    /**
     * Get all registered sites.
     *
     * @return array<string, SiteInterface>
     */
    public function all(): array
    {
        return $this->sites;
    }

    /**
     * Check if a domain exists.
     */
    public function exists(string $domain): bool
    {
        return isset($this->sites[$domain]);
    }

    /**
     * Get bookmarks/quick links for the browser homepage.
     *
     * @return array<array{domain: string, name: string, icon: string, description: string}>
     */
    public function getBookmarks(): array
    {
        $bookmarks = [];

        foreach ($this->sites as $site) {
            $bookmarks[] = [
                'domain' => $site->getDomain(),
                'name' => $site->getName(),
                'icon' => $site->getIcon(),
                'description' => $site->getDescription(),
            ];
        }

        return $bookmarks;
    }
}
