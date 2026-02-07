<?php

declare(strict_types=1);

namespace App\Services\Browser\Sites;

use App\Services\Browser\SiteInterface;

/**
 * Underground Forum - Community discussions and job postings.
 */
class ForumSite implements SiteInterface
{
    public function getDomain(): string
    {
        return 'forum.matrix';
    }

    public function getName(): string
    {
        return 'The Underground';
    }

    public function getIcon(): string
    {
        return '💬';
    }

    public function getDescription(): string
    {
        return 'Hacker community forum';
    }

    public function getNavigation(array $context = []): array
    {
        return [
            ['label' => 'Home', 'path' => '/', 'icon' => '🏠'],
            ['label' => 'Jobs', 'path' => '/jobs', 'icon' => '💼'],
            ['label' => 'Trading', 'path' => '/trading', 'icon' => '🔄'],
            ['label' => 'Help', 'path' => '/help', 'icon' => '❓'],
        ];
    }

    public function render(string $path, array $context = []): array
    {
        return match (true) {
            $path === '/' || $path === '' => $this->renderHome($context),
            $path === '/jobs' => $this->renderJobs($context),
            $path === '/trading' => $this->renderTrading($context),
            $path === '/help' => $this->renderHelp($context),
            default => $this->render404($path),
        };
    }

    private function renderHome(array $context): array
    {
        return [
            'title' => 'The Underground - Forum',
            'content' => <<<HTML
            <div class="forum-home">
                <div class="forum-header">
                    <h1>The Underground</h1>
                    <span class="forum-tagline">Where shadows meet</span>
                </div>

                <div class="forum-stats">
                    <div class="stat">
                        <span class="stat-value">2,847</span>
                        <span class="stat-label">Members</span>
                    </div>
                    <div class="stat">
                        <span class="stat-value">156</span>
                        <span class="stat-label">Online</span>
                    </div>
                    <div class="stat">
                        <span class="stat-value">12.4K</span>
                        <span class="stat-label">Posts</span>
                    </div>
                </div>

                <div class="forum-sections">
                    <div class="forum-section">
                        <h2>📢 Announcements</h2>
                        <div class="thread-list">
                            <div class="thread pinned">
                                <span class="thread-pin">📌</span>
                                <a href="#" class="thread-title">Forum Rules - READ FIRST</a>
                                <span class="thread-author">Admin</span>
                            </div>
                            <div class="thread">
                                <a href="#" class="thread-title">New exploit kits available in store</a>
                                <span class="thread-author">MatrixNet</span>
                            </div>
                        </div>
                    </div>

                    <div class="forum-section">
                        <h2>🔥 Hot Topics</h2>
                        <div class="thread-list">
                            <div class="thread hot">
                                <a href="#" class="thread-title">Meridian Corp - Anyone else seeing increased security?</a>
                                <span class="thread-replies">47 replies</span>
                            </div>
                            <div class="thread">
                                <a href="#" class="thread-title">Best approach for legacy-auth systems?</a>
                                <span class="thread-replies">23 replies</span>
                            </div>
                            <div class="thread">
                                <a href="#" class="thread-title">WARNING: Marcus is a fed (proof inside)</a>
                                <span class="thread-replies">89 replies</span>
                            </div>
                        </div>
                    </div>

                    <div class="forum-section">
                        <h2>💡 Recent Help</h2>
                        <div class="thread-list">
                            <div class="thread">
                                <a href="#" class="thread-title">How to reduce trace level quickly?</a>
                                <span class="thread-replies">12 replies</span>
                            </div>
                            <div class="thread">
                                <a href="#" class="thread-title">Scan command not finding ports</a>
                                <span class="thread-replies">5 replies</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            HTML,
            'links' => [],
        ];
    }

    private function renderJobs(array $context): array
    {
        return [
            'title' => 'Jobs - The Underground',
            'content' => <<<HTML
            <div class="forum-page">
                <h1>💼 Job Board</h1>
                <p class="page-intro">Contract work for skilled operators. Payment on completion.</p>

                <div class="job-list">
                    <div class="job-card featured">
                        <div class="job-header">
                            <span class="job-badge featured">FEATURED</span>
                            <span class="job-payout">₿ 15,000</span>
                        </div>
                        <h3>Meridian Corp - Data Extraction</h3>
                        <p>Extract personnel database from Meridian Corp servers. Time-sensitive.</p>
                        <div class="job-meta">
                            <span>Difficulty: <strong class="medium">Medium</strong></span>
                            <span>Posted by: <strong>Anonymous</strong></span>
                        </div>
                        <button class="btn-job">View Details</button>
                    </div>

                    <div class="job-card">
                        <div class="job-header">
                            <span class="job-badge">NEW</span>
                            <span class="job-payout">₿ 5,000</span>
                        </div>
                        <h3>ATM Network Mapping</h3>
                        <p>Map and document ATM network topology for unnamed client.</p>
                        <div class="job-meta">
                            <span>Difficulty: <strong class="easy">Easy</strong></span>
                            <span>Posted by: <strong>Ghost</strong></span>
                        </div>
                        <button class="btn-job">View Details</button>
                    </div>

                    <div class="job-card">
                        <div class="job-header">
                            <span class="job-payout">₿ 50,000</span>
                        </div>
                        <h3>TechDyne Industrial Espionage</h3>
                        <p>Retrieve prototype schematics from secure R&D servers.</p>
                        <div class="job-meta">
                            <span>Difficulty: <strong class="hard">Hard</strong></span>
                            <span>Posted by: <strong>Cipher</strong></span>
                        </div>
                        <button class="btn-job">View Details</button>
                    </div>

                    <div class="job-card urgent">
                        <div class="job-header">
                            <span class="job-badge urgent">URGENT</span>
                            <span class="job-payout">₿ 8,000</span>
                        </div>
                        <h3>Evidence Cleanup</h3>
                        <p>Remove all traces of recent operation from target logs.</p>
                        <div class="job-meta">
                            <span>Difficulty: <strong class="medium">Medium</strong></span>
                            <span>Expires: <strong>2 hours</strong></span>
                        </div>
                        <button class="btn-job">View Details</button>
                    </div>
                </div>
            </div>
            HTML,
            'links' => [],
        ];
    }

    private function renderTrading(array $context): array
    {
        return [
            'title' => 'Trading - The Underground',
            'content' => <<<HTML
            <div class="forum-page">
                <h1>🔄 Trading Post</h1>
                <p class="page-intro">Buy, sell, and trade with verified members.</p>

                <div class="trade-list">
                    <div class="trade-card selling">
                        <span class="trade-type">SELLING</span>
                        <h3>Fresh Corporate Credentials</h3>
                        <p>Valid admin credentials for mid-tier corp. DM for details.</p>
                        <div class="trade-meta">
                            <span class="trade-price">₿ 2,500</span>
                            <span class="trade-seller">Seller: DarkPhoenix (⭐ 4.8)</span>
                        </div>
                    </div>

                    <div class="trade-card buying">
                        <span class="trade-type">BUYING</span>
                        <h3>Zero-Day Exploits</h3>
                        <p>Looking for unpatched vulnerabilities. Paying top credits.</p>
                        <div class="trade-meta">
                            <span class="trade-price">₿ 5,000+</span>
                            <span class="trade-seller">Buyer: SilentRunner (⭐ 4.9)</span>
                        </div>
                    </div>

                    <div class="trade-card selling">
                        <span class="trade-type">SELLING</span>
                        <h3>Botnet Access (500 nodes)</h3>
                        <p>DDoS capable. Clean IPs. Monthly rental available.</p>
                        <div class="trade-meta">
                            <span class="trade-price">₿ 8,000/mo</span>
                            <span class="trade-seller">Seller: BotMaster (⭐ 4.6)</span>
                        </div>
                    </div>
                </div>

                <div class="trade-warning">
                    ⚠️ Always use escrow for trades. Report scammers to mods.
                </div>
            </div>
            HTML,
            'links' => [],
        ];
    }

    private function renderHelp(array $context): array
    {
        return [
            'title' => 'Help - The Underground',
            'content' => <<<HTML
            <div class="forum-page">
                <h1>❓ Help & Questions</h1>
                <p class="page-intro">Get help from the community. Search before posting.</p>

                <div class="help-categories">
                    <div class="help-category">
                        <h3>🔰 Beginner Questions</h3>
                        <ul class="help-threads">
                            <li><a href="#">How do I start my first hack?</a> <span>Answered</span></li>
                            <li><a href="#">What commands should I buy first?</a> <span>Answered</span></li>
                            <li><a href="#">How does trace work?</a> <span>Answered</span></li>
                        </ul>
                    </div>

                    <div class="help-category">
                        <h3>⚙️ Technical Issues</h3>
                        <ul class="help-threads">
                            <li><a href="#">Scan not detecting services</a> <span>3 replies</span></li>
                            <li><a href="#">Connection timeout errors</a> <span>7 replies</span></li>
                            <li><a href="#">Exploit failing on valid target</a> <span>12 replies</span></li>
                        </ul>
                    </div>

                    <div class="help-category">
                        <h3>💡 Tips & Strategies</h3>
                        <ul class="help-threads">
                            <li><a href="#">Best order to hack Meridian?</a> <span>Hot</span></li>
                            <li><a href="#">Minimizing trace during long ops</a> <span>Guide</span></li>
                            <li><a href="#">Efficient credit farming methods</a> <span>15 replies</span></li>
                        </ul>
                    </div>
                </div>

                <div class="help-post-new">
                    <button class="btn-new-thread">+ New Question</button>
                </div>
            </div>
            HTML,
            'links' => [],
        ];
    }

    private function render404(string $path): array
    {
        return [
            'title' => '404 - The Underground',
            'content' => <<<HTML
            <div class="error-page">
                <h1>404 - Thread Not Found</h1>
                <p>This thread may have been deleted or moved.</p>
                <a href="/" class="forum-link">Return to Forum</a>
            </div>
            HTML,
            'links' => [],
        ];
    }
}
