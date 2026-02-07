/**
 * Node Manager - Visual network topology viewer
 * Shows the player's position in the "matrix" and discovered nodes
 */
export function nodeManager() {
    return {
        nodes: [],
        connections: [],
        selectedNode: null,
        currentNodeId: null,
        viewOffset: { x: 0, y: 0 },
        isDragging: false,
        dragStart: { x: 0, y: 0 },
        scale: 1,

        init() {
            // Load mock network data
            this.loadNetworkData();

            // Set up canvas after next tick
            this.$nextTick(() => {
                this.setupCanvas();
                this.render();
            });
        },

        loadNetworkData() {
            // Mock network topology - in real game this comes from game state
            this.nodes = [
                {
                    id: 'local',
                    name: 'Your Machine',
                    type: 'local',
                    x: 400,
                    y: 250,
                    status: 'owned',
                    ip: '127.0.0.1',
                    info: 'Local development machine'
                },
                {
                    id: 'gateway-1',
                    name: 'Public Gateway',
                    type: 'gateway',
                    x: 250,
                    y: 150,
                    status: 'scanned',
                    ip: '203.0.113.1',
                    info: 'Entry point to Meridian network'
                },
                {
                    id: 'atm-241',
                    name: 'ATM-241',
                    type: 'device',
                    x: 100,
                    y: 80,
                    status: 'hacked',
                    ip: '192.168.50.15',
                    info: 'Meridian Corp ATM Terminal'
                },
                {
                    id: 'cam-241',
                    name: 'Camera-241',
                    type: 'camera',
                    x: 100,
                    y: 220,
                    status: 'discovered',
                    ip: '192.168.50.16',
                    info: 'Security camera - Lobby entrance'
                },
                {
                    id: 'light-ctrl',
                    name: 'Light Controller',
                    type: 'iot',
                    x: 50,
                    y: 150,
                    status: 'discovered',
                    ip: '192.168.50.17',
                    info: 'Building automation system'
                },
                {
                    id: 'auth-server',
                    name: 'Auth Server',
                    type: 'server',
                    x: 250,
                    y: 320,
                    status: 'locked',
                    ip: '192.168.50.10',
                    info: 'Authentication server - legacy-auth v2.1'
                },
                {
                    id: 'db-main',
                    name: 'Database',
                    type: 'database',
                    x: 400,
                    y: 400,
                    status: 'unknown',
                    ip: '192.168.50.25',
                    info: 'Primary database server'
                },
                {
                    id: 'banking-if',
                    name: 'Banking Interface',
                    type: 'server',
                    x: 550,
                    y: 320,
                    status: 'unknown',
                    ip: '192.168.50.30',
                    info: 'Core banking system interface'
                }
            ];

            this.connections = [
                { from: 'local', to: 'gateway-1' },
                { from: 'gateway-1', to: 'atm-241' },
                { from: 'atm-241', to: 'cam-241' },
                { from: 'atm-241', to: 'light-ctrl' },
                { from: 'gateway-1', to: 'auth-server' },
                { from: 'auth-server', to: 'db-main' },
                { from: 'auth-server', to: 'banking-if' },
                { from: 'db-main', to: 'banking-if' }
            ];

            this.currentNodeId = 'atm-241'; // Player is currently connected here
        },

        setupCanvas() {
            const canvas = this.$refs.canvas;
            if (!canvas) return;

            // Handle resize
            this.resizeCanvas();
            window.addEventListener('resize', () => this.resizeCanvas());
        },

        resizeCanvas() {
            const canvas = this.$refs.canvas;
            const container = canvas?.parentElement;
            if (!canvas || !container) return;

            canvas.width = container.clientWidth;
            canvas.height = container.clientHeight;
            this.render();
        },

        render() {
            const canvas = this.$refs.canvas;
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            // Apply view transform
            ctx.save();
            ctx.translate(this.viewOffset.x, this.viewOffset.y);
            ctx.scale(this.scale, this.scale);

            // Draw connections first (behind nodes)
            this.drawConnections(ctx);

            // Draw nodes
            this.drawNodes(ctx);

            ctx.restore();
        },

        drawConnections(ctx) {
            ctx.strokeStyle = '#2a2d36';
            ctx.lineWidth = 2;

            for (const conn of this.connections) {
                const fromNode = this.nodes.find(n => n.id === conn.from);
                const toNode = this.nodes.find(n => n.id === conn.to);
                if (!fromNode || !toNode) continue;

                // Check if connection is between accessible nodes
                const isActive = this.isNodeAccessible(fromNode) && this.isNodeAccessible(toNode);

                ctx.beginPath();
                ctx.strokeStyle = isActive ? '#0891b2' : '#2a2d36';
                ctx.setLineDash(isActive ? [] : [5, 5]);
                ctx.moveTo(fromNode.x, fromNode.y);
                ctx.lineTo(toNode.x, toNode.y);
                ctx.stroke();
                ctx.setLineDash([]);
            }
        },

        drawNodes(ctx) {
            for (const node of this.nodes) {
                this.drawNode(ctx, node);
            }
        },

        drawNode(ctx, node) {
            const isSelected = this.selectedNode?.id === node.id;
            const isCurrent = this.currentNodeId === node.id;
            const radius = 25;

            // Node colors based on status
            const colors = {
                owned: { fill: '#22d3ee', stroke: '#0891b2' },
                hacked: { fill: '#4ade80', stroke: '#16a34a' },
                scanned: { fill: '#fbbf24', stroke: '#d97706' },
                discovered: { fill: '#a1a1aa', stroke: '#71717a' },
                locked: { fill: '#f87171', stroke: '#dc2626' },
                unknown: { fill: '#52525b', stroke: '#3f3f46' }
            };

            const color = colors[node.status] || colors.unknown;

            // Draw glow for current node
            if (isCurrent) {
                ctx.beginPath();
                ctx.arc(node.x, node.y, radius + 10, 0, Math.PI * 2);
                ctx.fillStyle = 'rgba(34, 211, 238, 0.2)';
                ctx.fill();
            }

            // Draw selection ring
            if (isSelected) {
                ctx.beginPath();
                ctx.arc(node.x, node.y, radius + 5, 0, Math.PI * 2);
                ctx.strokeStyle = '#22d3ee';
                ctx.lineWidth = 2;
                ctx.stroke();
            }

            // Draw node circle
            ctx.beginPath();
            ctx.arc(node.x, node.y, radius, 0, Math.PI * 2);
            ctx.fillStyle = color.fill;
            ctx.fill();
            ctx.strokeStyle = color.stroke;
            ctx.lineWidth = 2;
            ctx.stroke();

            // Draw icon based on type
            ctx.fillStyle = '#0a0a0f';
            ctx.font = '14px "JetBrains Mono"';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';

            const icons = {
                local: '~',
                gateway: 'G',
                server: 'S',
                database: 'DB',
                device: 'D',
                camera: 'C',
                iot: 'IoT'
            };
            ctx.fillText(icons[node.type] || '?', node.x, node.y);

            // Draw label
            ctx.fillStyle = '#e4e4e7';
            ctx.font = '11px Inter';
            ctx.fillText(node.name, node.x, node.y + radius + 15);

            // Draw IP for discovered+ nodes
            if (node.status !== 'unknown') {
                ctx.fillStyle = '#71717a';
                ctx.font = '9px "JetBrains Mono"';
                ctx.fillText(node.ip, node.x, node.y + radius + 28);
            }
        },

        isNodeAccessible(node) {
            return ['owned', 'hacked', 'scanned'].includes(node.status);
        },

        handleCanvasClick(event) {
            const canvas = this.$refs.canvas;
            const rect = canvas.getBoundingClientRect();
            const x = (event.clientX - rect.left - this.viewOffset.x) / this.scale;
            const y = (event.clientY - rect.top - this.viewOffset.y) / this.scale;

            // Check if clicked on a node
            for (const node of this.nodes) {
                const dist = Math.sqrt((x - node.x) ** 2 + (y - node.y) ** 2);
                if (dist <= 25) {
                    this.selectedNode = node;
                    this.render();
                    return;
                }
            }

            // Clicked on empty space
            this.selectedNode = null;
            this.render();
        },

        handleCanvasMouseDown(event) {
            if (event.button === 0 && !this.getNodeAtPosition(event)) {
                this.isDragging = true;
                this.dragStart = { x: event.clientX - this.viewOffset.x, y: event.clientY - this.viewOffset.y };
            }
        },

        handleCanvasMouseMove(event) {
            if (this.isDragging) {
                this.viewOffset.x = event.clientX - this.dragStart.x;
                this.viewOffset.y = event.clientY - this.dragStart.y;
                this.render();
            }
        },

        handleCanvasMouseUp() {
            this.isDragging = false;
        },

        handleWheel(event) {
            event.preventDefault();
            const delta = event.deltaY > 0 ? 0.9 : 1.1;
            this.scale = Math.max(0.5, Math.min(2, this.scale * delta));
            this.render();
        },

        getNodeAtPosition(event) {
            const canvas = this.$refs.canvas;
            const rect = canvas.getBoundingClientRect();
            const x = (event.clientX - rect.left - this.viewOffset.x) / this.scale;
            const y = (event.clientY - rect.top - this.viewOffset.y) / this.scale;

            for (const node of this.nodes) {
                const dist = Math.sqrt((x - node.x) ** 2 + (y - node.y) ** 2);
                if (dist <= 25) return node;
            }
            return null;
        },

        getStatusLabel(status) {
            const labels = {
                owned: 'OWNED',
                hacked: 'COMPROMISED',
                scanned: 'SCANNED',
                discovered: 'DISCOVERED',
                locked: 'LOCKED',
                unknown: 'UNKNOWN'
            };
            return labels[status] || 'UNKNOWN';
        },

        getStatusColor(status) {
            const colors = {
                owned: 'os-accent',
                hacked: 'text-green-400',
                scanned: 'text-yellow-400',
                discovered: 'os-text-dim',
                locked: 'text-red-400',
                unknown: 'os-text-muted'
            };
            return colors[status] || 'os-text-muted';
        },

        centerView() {
            this.viewOffset = { x: 0, y: 0 };
            this.scale = 1;
            this.render();
        }
    };
}
