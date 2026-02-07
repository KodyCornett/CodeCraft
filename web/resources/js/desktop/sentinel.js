/**
 * Sentinel Component - Security monitoring system
 * Tracks exposure, incoming connections, and active threats
 */
export function sentinel() {
    return {
        status: null,
        connections: [],
        threats: [],
        events: [],
        isLoading: true,
        activeTab: 'monitor', // 'monitor', 'connections', 'log'
        selectedConnection: null,
        actionInProgress: false,
        lastUpdate: null,
        refreshInterval: null,

        init() {
            this.loadStatus();
            this.loadEvents();
            // Auto-refresh every 5 seconds
            this.refreshInterval = setInterval(() => {
                this.loadStatus();
            }, 5000);
        },

        destroy() {
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
            }
        },

        async loadStatus() {
            try {
                const response = await fetch('/api/sentinel/status');
                const data = await response.json();
                if (data.success) {
                    this.status = data.status;
                    this.connections = data.connections;
                    this.threats = data.threats;
                    this.lastUpdate = new Date();
                }
            } catch (e) {
                console.error('Failed to load sentinel status:', e);
            } finally {
                this.isLoading = false;
            }
        },

        async loadEvents() {
            try {
                const response = await fetch('/api/sentinel/events');
                const data = await response.json();
                if (data.success) {
                    this.events = data.events;
                }
            } catch (e) {
                console.error('Failed to load events:', e);
            }
        },

        async blockConnection(connectionId) {
            if (this.actionInProgress) return;
            this.actionInProgress = true;

            try {
                const response = await fetch('/api/sentinel/block', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({ connectionId }),
                });

                const data = await response.json();
                if (data.success) {
                    // Remove from connections list
                    this.connections = this.connections.filter(c => c.id !== connectionId);
                    this.threats = this.threats.filter(t => t.id !== connectionId);
                    this.selectedConnection = null;
                }
            } catch (e) {
                console.error('Failed to block connection:', e);
            } finally {
                this.actionInProgress = false;
            }
        },

        async traceConnection(connectionId) {
            if (this.actionInProgress) return;
            this.actionInProgress = true;

            try {
                const response = await fetch('/api/sentinel/trace', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({ connectionId }),
                });

                const data = await response.json();
                if (data.success) {
                    // Update connection with trace info
                    const conn = this.connections.find(c => c.id === connectionId);
                    if (conn) {
                        conn.traced = true;
                        conn.traceInfo = data;
                    }
                }
            } catch (e) {
                console.error('Failed to trace connection:', e);
            } finally {
                this.actionInProgress = false;
            }
        },

        async counterHack(connectionId) {
            if (this.actionInProgress) return;
            this.actionInProgress = true;

            try {
                const response = await fetch('/api/sentinel/counter-hack', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({ connectionId }),
                });

                const data = await response.json();
                if (data.success) {
                    // Remove threat and update exposure
                    this.connections = this.connections.filter(c => c.id !== connectionId);
                    this.threats = this.threats.filter(t => t.id !== connectionId);
                    this.selectedConnection = null;
                    // Reload status to get updated exposure
                    await this.loadStatus();
                } else {
                    // Failed - exposure increased
                    await this.loadStatus();
                }
            } catch (e) {
                console.error('Failed to counter-hack:', e);
            } finally {
                this.actionInProgress = false;
            }
        },

        selectConnection(connection) {
            this.selectedConnection = connection;
        },

        getExposureColor() {
            if (!this.status) return 'text-gray-400';
            const level = this.status.exposureLevel?.key;
            return {
                'minimal': 'text-green-400',
                'low': 'text-cyan-400',
                'moderate': 'text-yellow-400',
                'high': 'text-orange-400',
                'critical': 'text-red-400',
            }[level] || 'text-gray-400';
        },

        getExposureBarColor() {
            if (!this.status) return 'bg-gray-600';
            const level = this.status.exposureLevel?.key;
            return {
                'minimal': 'bg-green-500',
                'low': 'bg-cyan-500',
                'moderate': 'bg-yellow-500',
                'high': 'bg-orange-500',
                'critical': 'bg-red-500',
            }[level] || 'bg-gray-600';
        },

        getStatusColor() {
            if (!this.status) return 'text-gray-400';
            const s = this.status.status;
            if (s === 'SECURE') return 'text-green-400';
            if (s === 'MONITORING') return 'text-cyan-400';
            if (s === 'ELEVATED THREAT') return 'text-yellow-400';
            if (s === 'UNDER ATTACK') return 'text-red-400';
            return 'text-gray-400';
        },

        getThreatIcon(level) {
            return {
                'critical': '🔴',
                'high': '🟠',
                'medium': '🟡',
                'low': '⚪',
            }[level] || '⚪';
        },

        getTypeLabel(type) {
            return {
                'probe': 'PROBE',
                'scan': 'SCAN',
                'intrusion': 'INTRUSION',
                'exploit': 'EXPLOIT',
            }[type] || type.toUpperCase();
        },

        getEventIcon(type) {
            return {
                'probe_detected': '🔍',
                'scan_detected': '📡',
                'intrusion_attempt': '🚨',
                'firewall_block': '🛡️',
                'exposure_increase': '📈',
                'connection_blocked': '🚫',
                'counter_hack': '⚔️',
            }[type] || '📋';
        },

        getSeverityClass(severity) {
            return {
                'critical': 'text-red-400 bg-red-500/10',
                'warning': 'text-yellow-400 bg-yellow-500/10',
                'info': 'text-cyan-400 bg-cyan-500/10',
            }[severity] || 'text-gray-400';
        }
    };
}
