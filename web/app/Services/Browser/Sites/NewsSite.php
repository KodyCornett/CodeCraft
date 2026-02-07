<?php

declare(strict_types=1);

namespace App\Services\Browser\Sites;

use App\Services\Browser\SiteInterface;

/**
 * Matrix News - Hacker news and world events.
 */
class NewsSite implements SiteInterface
{
    public function getDomain(): string
    {
        return 'news.matrix';
    }

    public function getName(): string
    {
        return 'Matrix News';
    }

    public function getIcon(): string
    {
        return '📰';
    }

    public function getDescription(): string
    {
        return 'Underground news and intel';
    }

    public function getNavigation(array $context = []): array
    {
        return [
            ['label' => 'Headlines', 'path' => '/', 'icon' => '📰'],
            ['label' => 'Corp Watch', 'path' => '/corps', 'icon' => '🏢'],
            ['label' => 'Security', 'path' => '/security', 'icon' => '🔒'],
        ];
    }

    public function render(string $path, array $context = []): array
    {
        return match (true) {
            $path === '/' || $path === '' => $this->renderHome($context),
            $path === '/corps' => $this->renderCorpWatch($context),
            $path === '/security' => $this->renderSecurity($context),
            default => $this->render404($path),
        };
    }

    private function renderHome(array $context): array
    {
        return [
            'title' => 'Matrix News - Headlines',
            'content' => <<<HTML
            <div class="news-home">
                <div class="news-header">
                    <h1>Matrix News</h1>
                    <span class="news-date">Updated: 2 hours ago</span>
                </div>

                <div class="news-featured">
                    <article class="news-article featured">
                        <span class="article-tag hot">BREAKING</span>
                        <h2>Meridian Corp Data Breach Suspected</h2>
                        <p>Internal sources report unusual network activity at Meridian Corporation's main data center. Security teams are on high alert.</p>
                        <div class="article-meta">
                            <span>by <strong>ShadowWriter</strong></span>
                            <span>3 hours ago</span>
                        </div>
                    </article>
                </div>

                <div class="news-grid">
                    <article class="news-article">
                        <span class="article-tag">SECURITY</span>
                        <h3>New Zero-Day Found in Legacy Auth Systems</h3>
                        <p>Researchers have discovered a critical vulnerability affecting legacy-auth versions 1.0 through 2.3.</p>
                        <div class="article-meta">
                            <span>5 hours ago</span>
                        </div>
                    </article>

                    <article class="news-article">
                        <span class="article-tag">CORP</span>
                        <h3>TechDyne Announces AI Security Initiative</h3>
                        <p>Major security overhaul planned following string of successful attacks.</p>
                        <div class="article-meta">
                            <span>8 hours ago</span>
                        </div>
                    </article>

                    <article class="news-article">
                        <span class="article-tag">UNDERGROUND</span>
                        <h3>Ghost Market Reaches 1M Users</h3>
                        <p>Popular darknet marketplace celebrates milestone amid crackdown.</p>
                        <div class="article-meta">
                            <span>12 hours ago</span>
                        </div>
                    </article>

                    <article class="news-article">
                        <span class="article-tag">WARNING</span>
                        <h3>Fed Honeypot Detected on Matrix-7</h3>
                        <p>Community warns of fake exploit sellers. Known alias: "Marcus_Net"</p>
                        <div class="article-meta">
                            <span>1 day ago</span>
                        </div>
                    </article>
                </div>
            </div>
            HTML,
            'links' => [],
        ];
    }

    private function renderCorpWatch(array $context): array
    {
        return [
            'title' => 'Corp Watch - Matrix News',
            'content' => <<<HTML
            <div class="news-page">
                <h1>Corp Watch</h1>
                <p class="page-intro">Tracking corporate security and vulnerabilities.</p>

                <div class="corp-list">
                    <div class="corp-card">
                        <h3>Meridian Corporation</h3>
                        <div class="corp-status vulnerable">HIGH RISK</div>
                        <p>Financial services. Known vulnerabilities in legacy auth systems.</p>
                        <ul class="corp-intel">
                            <li>Primary target for current ops</li>
                            <li>Multiple entry points identified</li>
                            <li>Security response time: ~15 minutes</li>
                        </ul>
                    </div>

                    <div class="corp-card">
                        <h3>TechDyne Industries</h3>
                        <div class="corp-status moderate">MODERATE</div>
                        <p>Technology manufacturing. Recently upgraded security.</p>
                        <ul class="corp-intel">
                            <li>New firewall deployment in progress</li>
                            <li>AI-based intrusion detection</li>
                        </ul>
                    </div>

                    <div class="corp-card">
                        <h3>NovaSec</h3>
                        <div class="corp-status secure">HARDENED</div>
                        <p>Cybersecurity firm. Extremely difficult target.</p>
                        <ul class="corp-intel">
                            <li>Not recommended for inexperienced operators</li>
                            <li>Zero successful breaches on record</li>
                        </ul>
                    </div>
                </div>
            </div>
            HTML,
            'links' => [],
        ];
    }

    private function renderSecurity(array $context): array
    {
        return [
            'title' => 'Security Alerts - Matrix News',
            'content' => <<<HTML
            <div class="news-page">
                <h1>Security Alerts</h1>
                <p class="page-intro">Latest vulnerabilities and security advisories.</p>

                <div class="alert-list">
                    <div class="security-alert critical">
                        <div class="alert-header">
                            <span class="alert-severity">CRITICAL</span>
                            <span class="alert-date">Today</span>
                        </div>
                        <h3>CVE-2087-4421: Legacy Auth RCE</h3>
                        <p>Remote code execution in legacy-auth v2.1-2.3. Exploit available.</p>
                    </div>

                    <div class="security-alert high">
                        <div class="alert-header">
                            <span class="alert-severity">HIGH</span>
                            <span class="alert-date">Yesterday</span>
                        </div>
                        <h3>CVE-2087-4398: SSH Key Disclosure</h3>
                        <p>Weak key generation in certain SSH implementations.</p>
                    </div>

                    <div class="security-alert medium">
                        <div class="alert-header">
                            <span class="alert-severity">MEDIUM</span>
                            <span class="alert-date">3 days ago</span>
                        </div>
                        <h3>CVE-2087-4355: Database Info Leak</h3>
                        <p>Information disclosure in common database configurations.</p>
                    </div>
                </div>
            </div>
            HTML,
            'links' => [],
        ];
    }

    private function render404(string $path): array
    {
        return [
            'title' => '404 - Matrix News',
            'content' => <<<HTML
            <div class="error-page">
                <h1>404 - Page Not Found</h1>
                <p>Article not found.</p>
                <a href="/" class="news-link">Return to Headlines</a>
            </div>
            HTML,
            'links' => [],
        ];
    }
}
