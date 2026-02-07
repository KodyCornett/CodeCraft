<?php

declare(strict_types=1);

namespace App\Services\Browser\Sites;

use App\Services\Browser\SiteInterface;

/**
 * Hacking Wiki - Command documentation and tutorials.
 * Updates based on player's unlocked commands.
 */
class WikiSite implements SiteInterface
{
    public function getDomain(): string
    {
        return 'wiki.matrix';
    }

    public function getName(): string
    {
        return 'HackWiki';
    }

    public function getIcon(): string
    {
        return '📚';
    }

    public function getDescription(): string
    {
        return 'Command reference and hacking tutorials';
    }

    public function getNavigation(array $context = []): array
    {
        return [
            ['label' => 'Home', 'path' => '/', 'icon' => '🏠'],
            ['label' => 'Commands', 'path' => '/commands', 'icon' => '⌨️'],
            ['label' => 'Tutorials', 'path' => '/tutorials', 'icon' => '📖'],
            ['label' => 'Glossary', 'path' => '/glossary', 'icon' => '📝'],
        ];
    }

    public function render(string $path, array $context = []): array
    {
        return match (true) {
            $path === '/' || $path === '' => $this->renderHome($context),
            $path === '/commands' => $this->renderCommandList($context),
            str_starts_with($path, '/commands/') => $this->renderCommand(substr($path, 10), $context),
            $path === '/tutorials' => $this->renderTutorials($context),
            $path === '/glossary' => $this->renderGlossary($context),
            default => $this->render404($path),
        };
    }

    private function renderHome(array $context): array
    {
        $unlockedCount = count($context['unlockedCommands'] ?? ['help', 'ls', 'cd', 'cat', 'clear', 'scan', 'connect']);

        return [
            'title' => 'HackWiki - Home',
            'content' => <<<HTML
            <div class="wiki-home">
                <div class="wiki-header">
                    <h1>Welcome to HackWiki</h1>
                    <p class="subtitle">Your comprehensive guide to system infiltration</p>
                </div>

                <div class="wiki-stats">
                    <div class="stat-card">
                        <div class="stat-value">{$unlockedCount}</div>
                        <div class="stat-label">Commands Unlocked</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">3</div>
                        <div class="stat-label">Tutorials Available</div>
                    </div>
                </div>

                <div class="wiki-section">
                    <h2>Quick Start</h2>
                    <p>New to hacking? Start with these basics:</p>
                    <ul>
                        <li><a href="/commands/help" class="wiki-link">help</a> - List available commands</li>
                        <li><a href="/commands/scan" class="wiki-link">scan</a> - Discover network targets</li>
                        <li><a href="/commands/connect" class="wiki-link">connect</a> - Establish connections</li>
                    </ul>
                </div>

                <div class="wiki-section">
                    <h2>Recent Updates</h2>
                    <ul class="update-list">
                        <li><span class="update-date">Today</span> Added exploit command documentation</li>
                        <li><span class="update-date">Yesterday</span> Updated scan command parameters</li>
                    </ul>
                </div>
            </div>
            HTML,
            'links' => [],
        ];
    }

    private function renderCommandList(array $context): array
    {
        $commands = $this->getCommandData();
        $unlocked = $context['unlockedCommands'] ?? array_keys($commands);

        $commandHtml = '';
        foreach ($commands as $name => $data) {
            $isUnlocked = in_array($name, $unlocked);
            $statusClass = $isUnlocked ? 'unlocked' : 'locked';
            $statusBadge = $isUnlocked ? '<span class="badge-unlocked">UNLOCKED</span>' : '<span class="badge-locked">LOCKED</span>';

            $commandHtml .= <<<HTML
            <div class="command-card {$statusClass}">
                <div class="command-header">
                    <code class="command-name">{$name}</code>
                    {$statusBadge}
                </div>
                <p class="command-desc">{$data['short']}</p>
                <a href="/commands/{$name}" class="command-link">View Details →</a>
            </div>
            HTML;
        }

        return [
            'title' => 'Commands - HackWiki',
            'content' => <<<HTML
            <div class="wiki-page">
                <h1>Command Reference</h1>
                <p class="page-intro">Complete list of available hacking commands. Locked commands can be purchased from <a href="matrix://store.matrix" class="wiki-link">MatrixNet Store</a>.</p>

                <div class="command-grid">
                    {$commandHtml}
                </div>
            </div>
            HTML,
            'links' => [],
        ];
    }

    private function renderCommand(string $command, array $context): array
    {
        $commands = $this->getCommandData();

        if (!isset($commands[$command])) {
            return $this->render404("/commands/{$command}");
        }

        $data = $commands[$command];
        $unlocked = $context['unlockedCommands'] ?? array_keys($commands);
        $isUnlocked = in_array($command, $unlocked);

        $examplesHtml = '';
        foreach ($data['examples'] as $example) {
            $examplesHtml .= "<div class=\"example\"><code>{$example['cmd']}</code><p>{$example['desc']}</p></div>";
        }

        $flagsHtml = '';
        if (!empty($data['flags'])) {
            $flagsHtml = '<h2>Flags & Options</h2><table class="flags-table"><thead><tr><th>Flag</th><th>Description</th></tr></thead><tbody>';
            foreach ($data['flags'] as $flag => $desc) {
                $flagsHtml .= "<tr><td><code>{$flag}</code></td><td>{$desc}</td></tr>";
            }
            $flagsHtml .= '</tbody></table>';
        }

        $statusNotice = $isUnlocked ? '' : '<div class="locked-notice">🔒 This command is locked. Purchase it from the MatrixNet Store to unlock.</div>';

        return [
            'title' => "{$command} - HackWiki",
            'content' => <<<HTML
            <div class="wiki-page command-detail">
                {$statusNotice}

                <h1><code>{$command}</code></h1>
                <p class="command-full-desc">{$data['description']}</p>

                <h2>Syntax</h2>
                <pre class="syntax-block"><code>{$data['syntax']}</code></pre>

                <h2>Examples</h2>
                <div class="examples-list">
                    {$examplesHtml}
                </div>

                {$flagsHtml}

                <div class="command-meta">
                    <span class="meta-item">Category: <strong>{$data['category']}</strong></span>
                    <span class="meta-item">Trace Impact: <strong>{$data['trace']}</strong></span>
                </div>

                <a href="/commands" class="back-link">← Back to Commands</a>
            </div>
            HTML,
            'links' => [],
        ];
    }

    private function renderTutorials(array $context): array
    {
        return [
            'title' => 'Tutorials - HackWiki',
            'content' => <<<HTML
            <div class="wiki-page">
                <h1>Tutorials</h1>
                <p class="page-intro">Step-by-step guides for common hacking scenarios.</p>

                <div class="tutorial-list">
                    <div class="tutorial-card">
                        <h3>🎯 Your First Hack</h3>
                        <p>Learn the basics: scanning, connecting, and extracting data from a target.</p>
                        <span class="tutorial-difficulty easy">Beginner</span>
                    </div>

                    <div class="tutorial-card">
                        <h3>🔐 Bypassing Authentication</h3>
                        <p>Techniques for getting past login screens and auth systems.</p>
                        <span class="tutorial-difficulty medium">Intermediate</span>
                    </div>

                    <div class="tutorial-card">
                        <h3>👻 Covering Your Tracks</h3>
                        <p>How to minimize trace levels and avoid detection.</p>
                        <span class="tutorial-difficulty hard">Advanced</span>
                    </div>
                </div>
            </div>
            HTML,
            'links' => [],
        ];
    }

    private function renderGlossary(array $context): array
    {
        return [
            'title' => 'Glossary - HackWiki',
            'content' => <<<HTML
            <div class="wiki-page">
                <h1>Glossary</h1>
                <p class="page-intro">Common terms and their meanings.</p>

                <dl class="glossary-list">
                    <dt>Node</dt>
                    <dd>A device or system on a network that can be targeted or compromised.</dd>

                    <dt>Trace</dt>
                    <dd>The detection level that increases as you perform actions. If it reaches 100%, you're caught.</dd>

                    <dt>Exploit</dt>
                    <dd>A vulnerability in a system that can be used to gain unauthorized access.</dd>

                    <dt>Payload</dt>
                    <dd>Code or commands executed on a target system after successful exploitation.</dd>

                    <dt>Pivot</dt>
                    <dd>Using a compromised system to attack other systems on the same network.</dd>

                    <dt>Backdoor</dt>
                    <dd>A hidden access point installed on a compromised system for future entry.</dd>

                    <dt>Firewall</dt>
                    <dd>Security system that blocks unauthorized network access. Must be bypassed.</dd>

                    <dt>Root</dt>
                    <dd>The highest level of access on a system. Full control.</dd>
                </dl>
            </div>
            HTML,
            'links' => [],
        ];
    }

    private function render404(string $path): array
    {
        return [
            'title' => '404 - HackWiki',
            'content' => <<<HTML
            <div class="error-page">
                <h1>404 - Page Not Found</h1>
                <p>The page <code>{$path}</code> does not exist.</p>
                <a href="/" class="wiki-link">Return to Home</a>
            </div>
            HTML,
            'links' => [],
        ];
    }

    private function getCommandData(): array
    {
        return [
            'help' => [
                'short' => 'Display available commands',
                'description' => 'Shows a list of all commands currently available to you. As you unlock more commands, they will appear here.',
                'syntax' => 'help [command]',
                'category' => 'System',
                'trace' => 'None',
                'examples' => [
                    ['cmd' => 'help', 'desc' => 'List all available commands'],
                    ['cmd' => 'help scan', 'desc' => 'Show detailed help for the scan command'],
                ],
                'flags' => [],
            ],
            'ls' => [
                'short' => 'List directory contents',
                'description' => 'Displays files and folders in the current or specified directory.',
                'syntax' => 'ls [path] [flags]',
                'category' => 'Filesystem',
                'trace' => 'None',
                'examples' => [
                    ['cmd' => 'ls', 'desc' => 'List current directory'],
                    ['cmd' => 'ls /home/user/documents', 'desc' => 'List specific directory'],
                    ['cmd' => 'ls -la', 'desc' => 'List with details including hidden files'],
                ],
                'flags' => [
                    '-l' => 'Long format with details',
                    '-a' => 'Show hidden files',
                    '-h' => 'Human-readable file sizes',
                ],
            ],
            'cd' => [
                'short' => 'Change directory',
                'description' => 'Navigate to a different directory in the filesystem.',
                'syntax' => 'cd <path>',
                'category' => 'Filesystem',
                'trace' => 'None',
                'examples' => [
                    ['cmd' => 'cd documents', 'desc' => 'Enter documents folder'],
                    ['cmd' => 'cd ..', 'desc' => 'Go up one directory'],
                    ['cmd' => 'cd ~', 'desc' => 'Go to home directory'],
                ],
                'flags' => [],
            ],
            'cat' => [
                'short' => 'Display file contents',
                'description' => 'Reads and displays the contents of a file. Essential for gathering intelligence.',
                'syntax' => 'cat <filename>',
                'category' => 'Filesystem',
                'trace' => 'None',
                'examples' => [
                    ['cmd' => 'cat readme.txt', 'desc' => 'Display contents of readme.txt'],
                    ['cmd' => 'cat /etc/passwd', 'desc' => 'View system password file'],
                ],
                'flags' => [],
            ],
            'clear' => [
                'short' => 'Clear the terminal',
                'description' => 'Clears all text from the terminal screen.',
                'syntax' => 'clear',
                'category' => 'System',
                'trace' => 'None',
                'examples' => [
                    ['cmd' => 'clear', 'desc' => 'Clear the terminal'],
                ],
                'flags' => [],
            ],
            'scan' => [
                'short' => 'Scan for targets and open ports',
                'description' => 'Scans a target IP or network range for open ports and services. Essential first step in any hack.',
                'syntax' => 'scan <target> [flags]',
                'category' => 'Reconnaissance',
                'trace' => 'Low (+2%)',
                'examples' => [
                    ['cmd' => 'scan 192.168.1.1', 'desc' => 'Scan single target'],
                    ['cmd' => 'scan 192.168.1.0/24', 'desc' => 'Scan network range'],
                    ['cmd' => 'scan -p 22,80,443 target', 'desc' => 'Scan specific ports'],
                ],
                'flags' => [
                    '-p' => 'Specify ports to scan',
                    '-A' => 'Aggressive scan (more info, higher trace)',
                    '-sV' => 'Service version detection',
                ],
            ],
            'connect' => [
                'short' => 'Establish connection to target',
                'description' => 'Opens a connection to a remote system. Required before running exploits or accessing files.',
                'syntax' => 'connect <target> [port]',
                'category' => 'Network',
                'trace' => 'Low (+5%)',
                'examples' => [
                    ['cmd' => 'connect 192.168.1.1', 'desc' => 'Connect to target'],
                    ['cmd' => 'connect 192.168.1.1 22', 'desc' => 'Connect to specific port'],
                ],
                'flags' => [],
            ],
            'disconnect' => [
                'short' => 'Close active connection',
                'description' => 'Terminates the current connection to a remote system.',
                'syntax' => 'disconnect',
                'category' => 'Network',
                'trace' => 'None',
                'examples' => [
                    ['cmd' => 'disconnect', 'desc' => 'Close current connection'],
                ],
                'flags' => [],
            ],
            'exploit' => [
                'short' => 'Run exploit against target',
                'description' => 'Executes an exploit against the connected target to gain access. Requires valid exploit for target system.',
                'syntax' => 'exploit <exploit_name> [options]',
                'category' => 'Attack',
                'trace' => 'High (+15%)',
                'examples' => [
                    ['cmd' => 'exploit ssh_bruteforce', 'desc' => 'Run SSH brute force'],
                    ['cmd' => 'exploit buffer_overflow --target auth', 'desc' => 'Buffer overflow on auth service'],
                ],
                'flags' => [
                    '--target' => 'Specify target service',
                    '--payload' => 'Custom payload to deliver',
                ],
            ],
            'probe' => [
                'short' => 'Deep system analysis',
                'description' => 'Performs detailed analysis of a connected system to find vulnerabilities and useful data.',
                'syntax' => 'probe [target]',
                'category' => 'Reconnaissance',
                'trace' => 'Medium (+8%)',
                'examples' => [
                    ['cmd' => 'probe', 'desc' => 'Probe current connection'],
                    ['cmd' => 'probe auth-server', 'desc' => 'Probe specific system'],
                ],
                'flags' => [],
            ],
        ];
    }
}
