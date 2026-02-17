/**
 * Firewall Component - Repair and activate firewall after DEFRAG
 * Also shows proactive DEFRAG prompt when connection traces exist
 */
export function firewall() {
    return {
        status: 'active',
        repairProgress: 0,
        pollInterval: null,
        traceCount: 0,
        isOnLocalhost: true,
        defragActive: false,

        init() {
            this.loadStatus();
            this.pollInterval = setInterval(() => this.loadStatus(), 3000);
        },

        destroy() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
            }
        },

        async loadStatus() {
            try {
                const response = await fetch('/api/firewall/status', {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await response.json();
                if (data.success) {
                    if (this.status !== 'repairing') {
                        this.status = data.status;
                    }
                    this.traceCount = data.traceCount || 0;
                    this.isOnLocalhost = data.isOnLocalhost !== false;
                    this.defragActive = data.defragActive || false;
                }
            } catch (e) {
                console.error('Firewall status error:', e);
            }
        },

        async startRepair() {
            try {
                const response = await fetch('/api/firewall/repair', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                });
                const data = await response.json();
                if (data.success) {
                    this.status = 'repairing';
                    this.repairProgress = 0;
                    this.animateRepair();
                }
            } catch (e) {
                console.error('Firewall repair error:', e);
            }
        },

        animateRepair() {
            const duration = 5000;
            const steps = 50;
            const interval = duration / steps;
            let step = 0;

            const timer = setInterval(() => {
                step++;
                this.repairProgress = Math.min(100, Math.round((step / steps) * 100));
                if (step >= steps) {
                    clearInterval(timer);
                    this.status = 'repaired';
                }
            }, interval);
        },

        async activateFirewall() {
            try {
                const response = await fetch('/api/firewall/activate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                });
                const data = await response.json();
                if (data.success) {
                    this.status = 'active';
                    this.repairProgress = 0;
                }
            } catch (e) {
                console.error('Firewall activate error:', e);
            }
        },

        canShowDefrag() {
            return this.status === 'active' && this.traceCount > 0;
        },

        canRunDefrag() {
            return this.isOnLocalhost && !this.defragActive;
        },

        getStatusLabel() {
            return {
                'active': 'ACTIVE',
                'damaged': 'DAMAGED',
                'repairing': 'REPAIRING',
                'repaired': 'REPAIRED',
            }[this.status] || 'UNKNOWN';
        },

        getStatusColor() {
            return {
                'active': 'text-green-400',
                'damaged': 'text-red-400',
                'repairing': 'text-yellow-400',
                'repaired': 'text-cyan-400',
            }[this.status] || 'text-gray-400';
        },

        getStatusBgColor() {
            return {
                'active': 'bg-green-500/20 border-green-500/30',
                'damaged': 'bg-red-500/20 border-red-500/30',
                'repairing': 'bg-yellow-500/20 border-yellow-500/30',
                'repaired': 'bg-cyan-500/20 border-cyan-500/30',
            }[this.status] || 'bg-gray-500/20 border-gray-500/30';
        },
    };
}
