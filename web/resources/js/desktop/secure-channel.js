/**
 * Secure Channel Component - VPN simulation and job offer interface
 */
export function secureChannel() {
    return {
        // Connection state
        connectionPhase: 'disconnected', // 'connecting', 'connected'
        connectionSteps: [],
        connectionError: null,

        // Job state
        jobData: null,
        jobId: null,
        isLoadingJob: false,

        // Negotiation state
        showNegotiateWarning: false,
        isNegotiating: false,
        negotiationResult: null,

        // Puzzle state
        puzzleActive: false,
        puzzleContent: '',
        puzzleHint: '',
        puzzleAnswer: '',
        puzzleError: null,
        puzzleSolved: false,

        init() {
            // Get job ID from the window data via Alpine's magic
            this.$nextTick(() => {
                // Try to find the window object in the parent context
                // The window.jobId is set when openSecureChannel is called
                try {
                    // Access the parent window manager's scope
                    const windowManagerEl = document.querySelector('[x-data*="windowManager"]');
                    if (windowManagerEl && windowManagerEl.__x) {
                        const windowManager = windowManagerEl.__x.$data;
                        // Find our window by looking for secureChannel type
                        const ourWindow = windowManager.windows.find(w =>
                            w.type === 'secureChannel' && w.jobId
                        );
                        if (ourWindow) {
                            this.jobId = ourWindow.jobId;
                        }
                    }
                } catch (e) {
                    console.warn('Could not retrieve jobId from window manager:', e);
                }

                // Start VPN connection simulation
                this.simulateVpnConnection();
            });
        },

        async simulateVpnConnection() {
            this.connectionPhase = 'connecting';
            this.connectionSteps = [];

            const routes = [
                { ip: 'VPN194.45.1.2', location: 'Frankfurt, Germany', delay: 800 },
                { ip: 'VPN89.12.45.8', location: 'Stockholm, Sweden', delay: 600 },
                { ip: 'RELAY.onion', location: 'Anonymous Node', delay: 1000 },
            ];

            for (const route of routes) {
                this.connectionSteps.push({
                    ip: route.ip,
                    location: route.location,
                    status: 'routing'
                });

                await this.sleep(route.delay);

                // Update the last step to done
                this.connectionSteps[this.connectionSteps.length - 1].status = 'done';
            }

            // Final connection established message
            await this.sleep(500);
            this.connectionSteps.push({
                ip: null,
                location: null,
                status: 'complete',
                message: 'Secure channel established'
            });

            await this.sleep(300);
            this.connectionPhase = 'connected';

            // Load job data
            await this.loadJobData();
        },

        async loadJobData() {
            this.isLoadingJob = true;

            try {
                // Get jobId from context
                const jobId = this.getJobIdFromContext();
                this.jobId = jobId;

                const response = await fetch(`/api/jobs/${jobId}`);
                const data = await response.json();

                if (data.success) {
                    this.jobData = data.job;
                } else {
                    this.connectionError = data.error || 'Failed to load job data';
                }
            } catch (e) {
                console.error('Failed to load job:', e);
                this.connectionError = 'Connection interrupted. Try again later.';
            } finally {
                this.isLoadingJob = false;
            }
        },

        getJobIdFromContext() {
            // Return jobId if it was set during init, otherwise use default
            return this.jobId || 'nova-corp-hr';
        },

        showNegotiationWarning() {
            this.showNegotiateWarning = !this.showNegotiateWarning;
        },

        async attemptNegotiate() {
            if (this.isNegotiating) return;

            this.isNegotiating = true;
            this.negotiationResult = null;

            try {
                const response = await fetch(`/api/jobs/${this.jobId}/negotiate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                });

                const data = await response.json();
                this.negotiationResult = data;

                if (data.success && data.newPayout) {
                    this.jobData.payout = data.newPayout;
                }
            } catch (e) {
                console.error('Negotiation failed:', e);
                this.negotiationResult = {
                    success: false,
                    message: 'Connection lost during negotiation.'
                };
            } finally {
                this.isNegotiating = false;
            }
        },

        async acceptJob() {
            // Show the puzzle
            this.puzzleActive = true;
            this.puzzleError = null;
            this.puzzleAnswer = '';

            try {
                const response = await fetch(`/api/jobs/${this.jobId}/accept`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                });

                const data = await response.json();

                if (data.success) {
                    this.puzzleContent = data.puzzle;
                    this.puzzleHint = data.hint || 'Decode: A=1, B=2, C=3...';
                } else {
                    this.puzzleError = data.error || 'Failed to initialize verification';
                }
            } catch (e) {
                console.error('Failed to start puzzle:', e);
                this.puzzleError = 'Connection error. Try again.';
            }
        },

        async submitPuzzle() {
            if (!this.puzzleAnswer.trim()) return;

            this.puzzleError = null;

            try {
                const response = await fetch(`/api/jobs/${this.jobId}/verify-puzzle`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({
                        answer: this.puzzleAnswer.toUpperCase().trim()
                    }),
                });

                const data = await response.json();

                if (data.success) {
                    this.puzzleSolved = true;

                    // Dispatch event to refresh messages (encrypted message was created)
                    window.dispatchEvent(new CustomEvent('messages-updated'));

                    // Show success briefly then close or update view
                    setTimeout(() => {
                        // Could close the window or show mission details
                    }, 2000);
                } else {
                    this.puzzleError = data.error || 'Incorrect answer. Try again.';
                    this.puzzleAnswer = '';
                }
            } catch (e) {
                console.error('Puzzle verification failed:', e);
                this.puzzleError = 'Verification failed. Try again.';
            }
        },

        cancelPuzzle() {
            this.puzzleActive = false;
            this.puzzleContent = '';
            this.puzzleAnswer = '';
            this.puzzleError = null;
        },

        sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        },

        getDifficultyColor(difficulty) {
            switch (difficulty) {
                case 'easy': return 'text-green-400';
                case 'medium': return 'text-yellow-400';
                case 'hard': return 'text-red-400';
                case 'extreme': return 'text-purple-400';
                default: return 'text-gray-400';
            }
        },

        getDifficultyBgColor(difficulty) {
            switch (difficulty) {
                case 'easy': return 'bg-green-600/20';
                case 'medium': return 'bg-yellow-600/20';
                case 'hard': return 'bg-red-600/20';
                case 'extreme': return 'bg-purple-600/20';
                default: return 'bg-gray-600/20';
            }
        }
    };
}
