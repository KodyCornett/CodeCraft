<?php

declare(strict_types=1);

namespace App\Services\Browser\Sites;

use App\Services\Browser\SiteInterface;

/**
 * MatrixNet Store - Purchase commands, tools, and upgrades.
 */
class StoreSite implements SiteInterface
{
    public function getDomain(): string
    {
        return 'store.matrix';
    }

    public function getName(): string
    {
        return 'MatrixNet Store';
    }

    public function getIcon(): string
    {
        return '🛒';
    }

    public function getDescription(): string
    {
        return 'Purchase commands, exploits, and upgrades';
    }

    public function getNavigation(array $context = []): array
    {
        return [
            ['label' => 'Home', 'path' => '/', 'icon' => '🏠'],
            ['label' => 'Commands', 'path' => '/commands', 'icon' => '⌨️'],
            ['label' => 'Exploits', 'path' => '/exploits', 'icon' => '💉'],
            ['label' => 'Tools', 'path' => '/tools', 'icon' => '🔧'],
            ['label' => 'Upgrades', 'path' => '/upgrades', 'icon' => '⬆️'],
        ];
    }

    public function render(string $path, array $context = []): array
    {
        return match (true) {
            $path === '/' || $path === '' => $this->renderHome($context),
            $path === '/commands' => $this->renderCommands($context),
            $path === '/exploits' => $this->renderExploits($context),
            $path === '/tools' => $this->renderTools($context),
            $path === '/upgrades' => $this->renderUpgrades($context),
            default => $this->render404($path),
        };
    }

    private function renderHome(array $context): array
    {
        $credits = $context['credits'] ?? 5000;

        return [
            'title' => 'MatrixNet Store',
            'content' => <<<HTML
            <div class="store-home">
                <div class="store-header">
                    <div class="store-logo">
                        <span class="logo-icon">◈</span>
                        <span class="logo-text">MatrixNet</span>
                    </div>
                    <div class="credits-display">
                        <span class="credits-label">Credits</span>
                        <span class="credits-value">₿ {$credits}</span>
                    </div>
                </div>

                <div class="store-banner">
                    <h2>🔥 Featured This Week</h2>
                    <p>Advanced Exploit Kit - 30% OFF</p>
                </div>

                <div class="store-categories">
                    <a href="/commands" class="category-card">
                        <span class="category-icon">⌨️</span>
                        <h3>Commands</h3>
                        <p>Expand your terminal capabilities</p>
                    </a>

                    <a href="/exploits" class="category-card">
                        <span class="category-icon">💉</span>
                        <h3>Exploits</h3>
                        <p>Vulnerabilities for specific systems</p>
                    </a>

                    <a href="/tools" class="category-card">
                        <span class="category-icon">🔧</span>
                        <h3>Tools</h3>
                        <p>Software to enhance your ops</p>
                    </a>

                    <a href="/upgrades" class="category-card">
                        <span class="category-icon">⬆️</span>
                        <h3>Upgrades</h3>
                        <p>Improve your system capabilities</p>
                    </a>
                </div>

                <div class="store-notice">
                    <p>💡 <strong>Pro tip:</strong> Check wiki.matrix for command documentation before purchasing.</p>
                </div>
            </div>
            HTML,
            'links' => [],
        ];
    }

    private function renderCommands(array $context): array
    {
        $credits = $context['credits'] ?? 5000;
        $owned = $context['ownedCommands'] ?? ['help', 'ls', 'cd', 'cat', 'clear', 'scan', 'connect', 'disconnect'];

        $items = [
            ['id' => 'exploit', 'name' => 'exploit', 'price' => 2500, 'desc' => 'Execute exploits against vulnerable targets'],
            ['id' => 'probe', 'name' => 'probe', 'price' => 1500, 'desc' => 'Deep system analysis and vulnerability detection'],
            ['id' => 'inject', 'name' => 'inject', 'price' => 3000, 'desc' => 'Inject payloads into running processes'],
            ['id' => 'decrypt', 'name' => 'decrypt', 'price' => 2000, 'desc' => 'Decrypt encrypted files and data'],
            ['id' => 'tunnel', 'name' => 'tunnel', 'price' => 3500, 'desc' => 'Create encrypted tunnels to hide traffic'],
            ['id' => 'spoof', 'name' => 'spoof', 'price' => 1800, 'desc' => 'Spoof network identity and packets'],
        ];

        $itemsHtml = $this->renderProductGrid($items, $owned, $credits);

        return [
            'title' => 'Commands - MatrixNet Store',
            'content' => <<<HTML
            <div class="store-page">
                <div class="page-header">
                    <h1>Commands</h1>
                    <div class="credits-display small">₿ {$credits}</div>
                </div>

                <p class="page-intro">Unlock new terminal commands to expand your hacking capabilities.</p>

                <div class="product-grid">
                    {$itemsHtml}
                </div>
            </div>
            HTML,
            'links' => [],
        ];
    }

    private function renderExploits(array $context): array
    {
        $credits = $context['credits'] ?? 5000;
        $owned = $context['ownedExploits'] ?? [];

        $items = [
            ['id' => 'ssh_bruteforce', 'name' => 'SSH Bruteforce', 'price' => 1000, 'desc' => 'Dictionary attack against SSH services'],
            ['id' => 'buffer_overflow', 'name' => 'Buffer Overflow', 'price' => 2500, 'desc' => 'Generic buffer overflow exploit'],
            ['id' => 'sql_injection', 'name' => 'SQL Injection', 'price' => 1500, 'desc' => 'Database exploitation toolkit'],
            ['id' => 'legacy_auth_bypass', 'name' => 'Legacy Auth Bypass', 'price' => 3000, 'desc' => 'Bypass legacy-auth v1.x-2.x systems'],
            ['id' => 'zero_day_kit', 'name' => 'Zero-Day Kit', 'price' => 10000, 'desc' => 'Collection of undisclosed vulnerabilities'],
        ];

        $itemsHtml = $this->renderProductGrid($items, $owned, $credits);

        return [
            'title' => 'Exploits - MatrixNet Store',
            'content' => <<<HTML
            <div class="store-page">
                <div class="page-header">
                    <h1>Exploits</h1>
                    <div class="credits-display small">₿ {$credits}</div>
                </div>

                <p class="page-intro">Exploits for specific vulnerabilities. Match the exploit to your target.</p>

                <div class="product-grid">
                    {$itemsHtml}
                </div>
            </div>
            HTML,
            'links' => [],
        ];
    }

    private function renderTools(array $context): array
    {
        $credits = $context['credits'] ?? 5000;
        $owned = $context['ownedTools'] ?? [];

        $items = [
            ['id' => 'port_scanner_pro', 'name' => 'Port Scanner Pro', 'price' => 800, 'desc' => 'Advanced scanning with stealth mode'],
            ['id' => 'packet_sniffer', 'name' => 'Packet Sniffer', 'price' => 1200, 'desc' => 'Capture and analyze network traffic'],
            ['id' => 'keylogger', 'name' => 'Keylogger', 'price' => 1500, 'desc' => 'Record keystrokes on compromised systems'],
            ['id' => 'rootkit', 'name' => 'Rootkit', 'price' => 4000, 'desc' => 'Maintain persistent hidden access'],
            ['id' => 'vpn_chain', 'name' => 'VPN Chain', 'price' => 2000, 'desc' => 'Route through multiple VPNs'],
        ];

        $itemsHtml = $this->renderProductGrid($items, $owned, $credits);

        return [
            'title' => 'Tools - MatrixNet Store',
            'content' => <<<HTML
            <div class="store-page">
                <div class="page-header">
                    <h1>Tools</h1>
                    <div class="credits-display small">₿ {$credits}</div>
                </div>

                <p class="page-intro">Software tools to enhance your operations.</p>

                <div class="product-grid">
                    {$itemsHtml}
                </div>
            </div>
            HTML,
            'links' => [],
        ];
    }

    private function renderUpgrades(array $context): array
    {
        $credits = $context['credits'] ?? 5000;
        $owned = $context['ownedUpgrades'] ?? [];

        $items = [
            ['id' => 'cpu_boost', 'name' => 'CPU Boost', 'price' => 3000, 'desc' => 'Faster command execution time'],
            ['id' => 'trace_reducer', 'name' => 'Trace Reducer', 'price' => 5000, 'desc' => 'Reduce trace accumulation by 25%'],
            ['id' => 'memory_expand', 'name' => 'Memory Expansion', 'price' => 2500, 'desc' => 'Store more data during ops'],
            ['id' => 'firewall_bypass', 'name' => 'Firewall Bypass Module', 'price' => 4500, 'desc' => 'Automatically bypass basic firewalls'],
            ['id' => 'neural_boost', 'name' => 'Neural Boost', 'price' => 15000, 'desc' => 'Unlock advanced hacking techniques'],
        ];

        $itemsHtml = $this->renderProductGrid($items, $owned, $credits);

        return [
            'title' => 'Upgrades - MatrixNet Store',
            'content' => <<<HTML
            <div class="store-page">
                <div class="page-header">
                    <h1>System Upgrades</h1>
                    <div class="credits-display small">₿ {$credits}</div>
                </div>

                <p class="page-intro">Permanent improvements to your system capabilities.</p>

                <div class="product-grid">
                    {$itemsHtml}
                </div>
            </div>
            HTML,
            'links' => [],
        ];
    }

    private function renderProductGrid(array $items, array $owned, int $credits): string
    {
        $html = '';

        foreach ($items as $item) {
            $isOwned = in_array($item['id'], $owned);
            $canAfford = $credits >= $item['price'];

            if ($isOwned) {
                $buttonHtml = '<button class="btn-owned" disabled>Owned ✓</button>';
                $cardClass = 'owned';
            } elseif ($canAfford) {
                $buttonHtml = '<button class="btn-buy" data-id="' . $item['id'] . '">Purchase</button>';
                $cardClass = 'available';
            } else {
                $buttonHtml = '<button class="btn-disabled" disabled>Insufficient Credits</button>';
                $cardClass = 'locked';
            }

            $html .= <<<HTML
            <div class="product-card {$cardClass}">
                <div class="product-header">
                    <h3>{$item['name']}</h3>
                    <span class="product-price">₿ {$item['price']}</span>
                </div>
                <p class="product-desc">{$item['desc']}</p>
                {$buttonHtml}
            </div>
            HTML;
        }

        return $html;
    }

    private function render404(string $path): array
    {
        return [
            'title' => '404 - MatrixNet Store',
            'content' => <<<HTML
            <div class="error-page">
                <h1>404 - Page Not Found</h1>
                <p>The page <code>{$path}</code> does not exist.</p>
                <a href="/" class="store-link">Return to Store</a>
            </div>
            HTML,
            'links' => [],
        ];
    }
}
