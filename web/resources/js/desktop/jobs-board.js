/**
 * Jobs Board Component - Active job tracking and available missions
 */
export function jobsBoard() {
    return {
        tab: 'active',           // 'active' | 'board' | 'history'
        activeMission: null,
        availableJobs: [],
        isLoading: false,
        pollInterval: null,
        selectedJobId: null,
        accepting: false,
        acceptResult: null,
        decryptingMissionId: null,
        decryptionProgress: 0,

        init() {
            this.loadActiveMission();
            this.pollInterval = setInterval(() => this.loadActiveMission(), 5000);
            this.$watch('tab', (val) => {
                if (val === 'board') this.loadAvailableJobs();
            });

            // Listen for mission accepted from terminal
            window.addEventListener('terminal:mission-accepted', (event) => {
                console.log('[Jobs Board] Mission accepted via terminal:', event.detail.missionId);
                this.loadActiveMission();
                // Switch to Active tab immediately
                setTimeout(() => { this.tab = 'active'; }, 100);
            });

            // Listen for mission completed to refresh state
            window.addEventListener('messages-updated', () => {
                console.log('[Jobs Board] Messages updated, refreshing active mission');
                this.loadActiveMission();
            });
        },

        destroy() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
            }
        },

        async loadActiveMission() {
            try {
                const response = await fetch('/api/mission/active');
                const data = await response.json();
                if (data.success) {
                    this.activeMission = data.active ? data.mission : null;
                }
            } catch (e) {
                console.error('Failed to load active mission:', e);
            } finally {
                this.isLoading = false;
            }
        },

        async loadAvailableJobs() {
            this.isLoading = true;
            try {
                const response = await fetch('/api/missions/available');
                const data = await response.json();
                if (data.success) {
                    this.availableJobs = data.missions || [];
                }
            } catch (e) {
                console.error('Failed to load available missions:', e);
            } finally {
                this.isLoading = false;
            }
        },

        objectiveIcon(obj) {
            if (obj.completed) return '✓';
            if (obj.failed) return '✗';
            return '○';
        },

        objectiveClass(obj) {
            if (obj.completed) return 'text-green-400';
            if (obj.failed) return 'text-red-400';
            return 'text-gray-500';
        },

        objectiveDescClass(obj) {
            if (obj.completed) return 'text-green-300';
            if (obj.failed) return 'line-through text-gray-600';
            return 'os-text-dim';
        },

        difficultyLabel(n) {
            if (n <= 3) return 'EASY';
            if (n <= 6) return 'MEDIUM';
            return 'HARD';
        },

        difficultyClass(n) {
            if (n <= 3) return 'text-green-400';
            if (n <= 6) return 'text-yellow-400';
            return 'text-red-400';
        },

        difficultyStars(n) {
            return '★'.repeat(n) + '☆'.repeat(10 - n);
        },

        elapsedTime(seconds) {
            if (!seconds && seconds !== 0) return '--:--';
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        },

        factionColor(contact) {
            const colors = {
                'ghost': 'text-cyan-400',
                'cipher': 'text-purple-400',
                'zero': 'text-red-400',
                'zer0': 'text-red-400',
                'lena': 'text-yellow-400',
                'hale': 'text-red-500',
            };
            return colors[(contact || '').toLowerCase()] || 'text-gray-400';
        },

        formatObjectiveType(type) {
            const labels = {
                'EXFILTRATE': 'EXFIL',
                'CONNECT': 'CONN',
                'DISCONNECT': 'DISC',
                'DOWNLOAD': 'DL',
                'EXECUTE': 'EXEC',
                'REMAIN_UNDETECTED': 'STEALTH',
                'SCAN': 'SCAN',
                'PROBE': 'PROBE',
                'ACCESS': 'ACCESS',
            };
            return labels[type] || (type || '').substring(0, 6);
        },

        selectMission(missionId) {
            const mission = this.availableJobs.find(j => j.id === missionId);

            if (!mission) return;

            this.selectedJobId = missionId;
            this.acceptResult = null;

            // If not decrypted yet, trigger animation
            if (!mission.decrypted) {
                this.startDecryptionAnimation(missionId);
            }
        },

        /**
         * Decryption animation (1.5 seconds)
         */
        async startDecryptionAnimation(missionId) {
            this.decryptingMissionId = missionId;
            this.decryptionProgress = 0;

            // Animate progress bar 0% → 100% over 1.5s
            const duration = 1500;
            const steps = 30;
            const stepDuration = duration / steps;

            for (let i = 0; i <= steps; i++) {
                this.decryptionProgress = (i / steps) * 100;
                await new Promise(resolve => setTimeout(resolve, stepDuration));
            }

            // Call backend to mark as decrypted
            try {
                await fetch(`/api/mission/${missionId}/decrypt`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                });

                // Update local state
                const mission = this.availableJobs.find(j => j.id === missionId);
                if (mission) {
                    mission.decrypted = true;
                }
            } catch (error) {
                console.error('Failed to decrypt mission:', error);
            }

            this.decryptingMissionId = null;
        },

        /**
         * Check if mission is currently decrypting
         */
        isDecrypting(missionId) {
            return this.decryptingMissionId === missionId;
        },

        /**
         * Get decryption progress (0-100)
         */
        getDecryptionProgress(missionId) {
            return this.isDecrypting(missionId) ? this.decryptionProgress : 0;
        },

        /**
         * Generate random hex noise for visual effect
         */
        generateHexNoise() {
            const chars = '0123456789ABCDEF';
            let noise = '';
            for (let i = 0; i < 200; i++) {
                noise += chars[Math.floor(Math.random() * chars.length)];
                if (i % 40 === 39) noise += '\n';
            }
            return noise;
        },

        async acceptMission(missionId) {
            if (this.accepting) return;
            this.accepting = true;
            this.acceptResult = null;
            try {
                const response = await fetch(`/api/mission/${missionId}/accept`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                });
                const data = await response.json();
                if (data.success) {
                    this.acceptResult = { success: true, message: 'Job accepted.' };

                    // Handle game events (tutorial messages, etc.)
                    if (data.gameEvents && data.gameEvents.length > 0) {
                        console.log('[Jobs Board] Game events received:', data.gameEvents);
                        // Dispatch messages-updated event to trigger message reload
                        window.dispatchEvent(new CustomEvent('messages-updated'));
                    }

                    await this.loadActiveMission();
                    // Remove accepted mission from board
                    this.availableJobs = this.availableJobs.filter(j => j.id !== missionId);
                    // Switch to ACTIVE tab immediately
                    setTimeout(() => { this.tab = 'active'; }, 300);
                } else {
                    this.acceptResult = { success: false, message: data.output || 'Failed to accept job.' };
                }
            } catch (e) {
                this.acceptResult = { success: false, message: 'Connection error.' };
            } finally {
                this.accepting = false;
            }
        },

        isJobActive(missionId) {
            return this.activeMission?.missionId === missionId;
        },

        hasActiveMission() {
            return this.activeMission !== null;
        },

        missionHeaderClass() {
            if (!this.activeMission) return 'border-gray-700';
            if (this.activeMission.failed) return 'border-red-500';
            if (this.activeMission.isComplete) return 'border-green-500';
            return 'border-cyan-600';
        },

        allObjectives() {
            if (!this.activeMission) return [];
            return this.activeMission.objectives || [];
        },

        bonusObjectives() {
            if (!this.activeMission) return [];
            return this.activeMission.bonusObjectives || [];
        },

        completedCount() {
            return this.allObjectives().filter(o => o.completed).length;
        },

        totalCount() {
            return this.allObjectives().length;
        },
    };
}
