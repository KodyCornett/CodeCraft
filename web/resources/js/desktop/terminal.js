/**
 * Terminal Component - Command input and output with typing effect
 */
export function terminal() {
    return {
        history: [],
        commandHistory: [],
        historyIndex: -1,
        currentInput: '',
        isProcessing: false,
        currentPath: '/home/user',

        // Connection state
        connectedTo: null,
        connectedToName: null,
        pendingConnection: null,

        init() {
            this.addOutput('CodeCraft Terminal v0.1.0', 'system');
            this.addOutput('Type "help" for available commands.', 'system');
            this.addOutput('', 'blank');

            // Focus input on init
            this.$nextTick(() => {
                this.$refs.input?.focus();
            });
        },

        get prompt() {
            const currentPath = this.currentPath || '/home/user';

            if (this.connectedTo && this.connectedTo !== 'localhost') {
                const path = currentPath === '/' ? '/' : currentPath;
                const shortName = this.connectedToName?.replace(/\s+/g, '').substring(0, 12) || this.connectedTo;
                return `root@${shortName}:${path}# `;
            }
            const path = currentPath.replace('/home/user', '~');
            return `user@localhost:${path}$ `;
        },

        async executeCommand() {
            if (this.isProcessing || !this.currentInput.trim()) return;

            const command = this.currentInput.trim();
            this.currentInput = '';
            this.commandHistory.push(command);
            this.historyIndex = this.commandHistory.length;

            // Show command in output
            this.addOutput(this.prompt + command, 'command');

            // Handle clear locally
            if (command === 'clear') {
                this.history = [];
                return;
            }

            this.isProcessing = true;

            try {
                const response = await fetch('/api/terminal', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({
                        command,
                        context: {
                            currentPath: this.currentPath,
                            connectedTo: this.connectedTo,
                        }
                    }),
                });

                const result = await response.json();

                // Handle state changes
                if (result.stateChanges) {
                    console.log('[Terminal] Received stateChanges:', result.stateChanges);
                    console.log('[Terminal] stateChanges.connectedTo type:', typeof result.stateChanges.connectedTo);
                    console.log('[Terminal] stateChanges.connectedTo value:', result.stateChanges.connectedTo);
                    console.log('[Terminal] BEFORE applyStateChanges - connectedTo:', this.connectedTo, 'prompt:', this.prompt);
                    this.applyStateChanges(result.stateChanges);
                    console.log('[Terminal] AFTER applyStateChanges - connectedTo:', this.connectedTo, 'prompt:', this.prompt);
                }

                // Type out the output with delay
                if (result.delayMs > 0 && result.lines.length > 0) {
                    await this.typeOutput(result.lines, result.delayMs);
                } else if (result.output) {
                    this.addOutput(result.output, result.success ? 'output' : 'error');
                }

            } catch (error) {
                this.addOutput('Error: Connection to system failed.', 'error');
                console.error('Terminal error:', error);
            } finally {
                this.isProcessing = false;
                this.$nextTick(() => {
                    this.scrollToBottom();
                    this.$refs.input?.focus();
                });
            }
        },

        applyStateChanges(stateChanges) {
            console.log('[Terminal] applyStateChanges called with:', stateChanges);
            console.log('[Terminal] Before update - connectedTo:', this.connectedTo, 'currentPath:', this.currentPath);

            // Handle path changes
            if (stateChanges.currentPath !== undefined) {
                this.currentPath = stateChanges.currentPath;
                console.log('[Terminal] Updated currentPath to:', this.currentPath);
            }

            // Handle connection changes
            if (stateChanges.connectedTo !== undefined) {
                const wasConnected = this.connectedTo;
                this.connectedTo = stateChanges.connectedTo;
                this.connectedToName = stateChanges.connectedToName || null;

                console.log('[Terminal] After update - connectedTo:', this.connectedTo, 'connectedToName:', this.connectedToName);

                // Reset path when connecting/disconnecting
                if (stateChanges.connectedTo && !wasConnected) {
                    // Connecting to new node
                    this.currentPath = stateChanges.rootPath || stateChanges.currentPath || '/';
                } else if (!stateChanges.connectedTo && wasConnected) {
                    // Disconnecting
                    this.currentPath = '/home/user';
                }

                // Force Alpine.js to re-evaluate prompt getter
                this.$nextTick(() => {
                    // Trigger DOM update by accessing the prompt
                    const newPrompt = this.prompt;
                    console.log('[Terminal] Prompt updated to:', newPrompt);
                });

                // Dispatch event to Node Manager
                window.dispatchEvent(new CustomEvent('terminal:connection-changed', {
                    detail: {
                        connectedTo: this.connectedTo,
                        connectedToName: this.connectedToName
                    }
                }));
            }

            // Handle pending connection (for puzzles)
            if (stateChanges.pendingConnection !== undefined) {
                this.pendingConnection = stateChanges.pendingConnection;
            }

            // Handle discovered nodes
            if (stateChanges.discoveredNodes) {
                window.dispatchEvent(new CustomEvent('terminal:nodes-discovered', {
                    detail: { nodes: stateChanges.discoveredNodes }
                }));
            }

            // Handle trace increase notification
            if (stateChanges.traceIncrease) {
                window.dispatchEvent(new CustomEvent('terminal:trace-increased', {
                    detail: { amount: stateChanges.traceIncrease }
                }));
            }

            // Handle Sentinel attack — force disconnect on client
            if (stateChanges.sentinelAttack) {
                this.connectedTo = null;
                this.connectedToName = null;
                this.currentPath = '/home/user';
                window.dispatchEvent(new CustomEvent('terminal:connection-changed', {
                    detail: { connectedTo: null, connectedToName: null }
                }));
            }

            // Handle DEFRAG complete — refresh messages
            if (stateChanges.defragComplete) {
                window.dispatchEvent(new CustomEvent('messages-updated'));
            }

            // Handle mission accepted — notify Jobs Board to switch to Active tab
            if (stateChanges.activeMission !== undefined) {
                console.log('[Terminal] Mission accepted:', stateChanges.activeMission);
                window.dispatchEvent(new CustomEvent('terminal:mission-accepted', {
                    detail: { missionId: stateChanges.activeMission }
                }));
            }

            // Handle mission complete — refresh messages
            if (stateChanges.missionCompleted) {
                console.log('[Terminal] Mission completed, dispatching messages-updated event');
                window.dispatchEvent(new CustomEvent('messages-updated'));
            }

            // Handle window open request (from mail command, etc.)
            if (stateChanges.openWindow) {
                console.log('[Terminal] Opening window:', stateChanges.openWindow);
                window.dispatchEvent(new CustomEvent('open-window', {
                    detail: { windowId: stateChanges.openWindow }
                }));
            }

            // Handle shield activation — notify Sentinel to refresh
            if (stateChanges.shieldActivated) {
                window.dispatchEvent(new CustomEvent('terminal:shield-activated'));
            }
        },

        async typeOutput(lines, totalDelay) {
            const delayPerLine = Math.min(50, totalDelay / Math.max(lines.length, 1));

            for (const line of lines) {
                this.addOutput(line, 'output');
                this.scrollToBottom();
                await this.sleep(delayPerLine);
            }
        },

        addOutput(text, type = 'output') {
            this.history.push({ text, type, id: Date.now() + Math.random() });
        },

        scrollToBottom() {
            this.$nextTick(() => {
                const container = this.$refs.outputContainer;
                if (container) {
                    container.scrollTop = container.scrollHeight;
                }
            });
        },

        sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        },

        handleKeydown(event) {
            if (event.key === 'ArrowUp') {
                event.preventDefault();
                this.navigateHistory(-1);
            } else if (event.key === 'ArrowDown') {
                event.preventDefault();
                this.navigateHistory(1);
            }
        },

        navigateHistory(direction) {
            const newIndex = this.historyIndex + direction;

            if (newIndex < 0) {
                return;
            }

            if (newIndex >= this.commandHistory.length) {
                this.historyIndex = this.commandHistory.length;
                this.currentInput = '';
                return;
            }

            this.historyIndex = newIndex;
            this.currentInput = this.commandHistory[newIndex];
        },

        focusInput() {
            this.$refs.input?.focus();
        },

        getLineClass(type) {
            const classes = {
                'command': 'text-[--color-text-primary]',
                'output': 'text-[--color-text-secondary]',
                'error': 'text-[--color-danger]',
                'system': 'text-[--color-accent-dim]',
                'blank': '',
            };
            return classes[type] || classes.output;
        }
    };
}
