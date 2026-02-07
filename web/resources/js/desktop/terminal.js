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
            const path = this.currentPath.replace('/home/user', '~');
            return `user@codecraft:${path}$ `;
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
                        }
                    }),
                });

                const result = await response.json();

                // Apply state changes
                if (result.stateChanges?.currentPath) {
                    this.currentPath = result.stateChanges.currentPath;
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
