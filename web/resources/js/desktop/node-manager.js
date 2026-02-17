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
        resizeObserver: null,
        animationFrame: null,
        packetPhase: 0,
        pulsePhase: 0,
        isDisconnecting: false,

        init() {
            this.loadNetworkData();
            this.setupEventListeners();

            this.$nextTick(() => {
                this.setupCanvas();
                this.startAnimation();
            });
        },

        destroy() {
            if (this.resizeObserver) {
                this.resizeObserver.disconnect();
                this.resizeObserver = null;
            }
            if (this.animationFrame) {
                cancelAnimationFrame(this.animationFrame);
                this.animationFrame = null;
            }
        },

        setupEventListeners() {
            window.addEventListener('terminal:nodes-discovered', (e) => {
                this.handleNodesDiscovered(e.detail.nodes);
            });

            window.addEventListener('terminal:connection-changed', (e) => {
                this.handleConnectionChanged(e.detail);
            });
        },

        // --- Active path tracing (HOME → GATEWAY → TARGET) ---

        getActivePath() {
            if (!this.currentNodeId || this.currentNodeId === 'local') return new Set(['local']);
            const parentMap = this.getParentMap();
            const path = new Set();
            let current = this.currentNodeId;
            let safety = 0;
            while (current && safety++ < 20) {
                path.add(current);
                current = parentMap[current];
            }
            path.add('local');
            return path;
        },

        // --- Parent map and depth calculation ---

        getParentMap() {
            const parentMap = {};
            this.connections.forEach(c => {
                if (!parentMap[c.to]) parentMap[c.to] = c.from;
            });
            return parentMap;
        },

        getNodeDepth(nodeId) {
            const parentMap = this.getParentMap();
            let depth = 0;
            let current = nodeId;
            while (parentMap[current]) {
                current = parentMap[current];
                depth++;
                if (depth > 20) break; // guard against cycles
            }
            return depth;
        },

        getNodesAtDepth(depth) {
            return this.nodes.filter(n => this.getNodeDepth(n.id) === depth);
        },

        // --- Tree layout positioning ---

        layoutNodes() {
            const canvas = this.$refs.canvas;
            if (!canvas) return;

            const canvasH = canvas.height;
            const startX = 120;
            const spacingX = 180;

            // Group nodes by depth
            const depthGroups = {};
            for (const node of this.nodes) {
                const depth = this.getNodeDepth(node.id);
                if (!depthGroups[depth]) depthGroups[depth] = [];
                depthGroups[depth].push(node);
            }

            // Position each group
            for (const [depth, group] of Object.entries(depthGroups)) {
                const d = parseInt(depth);
                const x = startX + d * spacingX;
                const totalHeight = (group.length - 1) * 90;
                const startY = (canvasH / 2) - (totalHeight / 2);

                group.forEach((node, i) => {
                    node.x = x;
                    node.y = startY + i * 90;
                });
            }
        },

        handleNodesDiscovered(nodes) {
            // Debug logging
            if (nodes.length > 0) {
                console.log('[NodeManager] Nodes discovered:', nodes.length);
                nodes.forEach(n => {
                    if (!n.name || !n.ip || n.name === 'undefined' || n.ip === 'undefined') {
                        console.warn('[NodeManager] Invalid node data:', n);
                    }
                });
            }

            // If the array contains raw string IDs (Kotlin engine path), trigger a
            // full network-state re-fetch to get proper node objects with isPublic etc.
            if (nodes.length > 0 && typeof nodes[0] === 'string') {
                this.loadNetworkData();
                return;
            }

            const statusRank = { unknown: 0, discovered: 1, scanned: 2, hacked: 3, compromised: 3, owned: 4 };

            let added = false;
            nodes.forEach(nodeData => {
                // Client-side visibility guard — skip only explicitly hidden private nodes
                if (nodeData.isPublic === false && nodeData.isDiscovered === false) return;

                const exists = this.nodes.find(n => n.id === nodeData.id);
                if (!exists) {
                    // Add node with temporary position — layoutNodes will fix it
                    const newNode = {
                        id: nodeData.id,
                        name: nodeData.name || nodeData.id || 'Unknown Node',
                        type: this.mapNodeType(nodeData.type),
                        x: 0,
                        y: 0,
                        status: nodeData.status || 'discovered',
                        ip: nodeData.ip || this.generateIpFromGateway(nodeData.id) || '0.0.0.0',
                        isPublic: Boolean(nodeData.isPublic),
                        isDiscovered: nodeData.isDiscovered !== false,
                        securityLevel: nodeData.securityLevel ?? null,
                        info: `Discovered: ${nodeData.name || nodeData.id || 'Unknown'}`
                    };

                    this.nodes.push(newNode);
                    added = true;
                    this.flashNode(newNode);
                } else {
                    // Upgrade status if incoming rank is higher
                    const incoming = statusRank[nodeData.status] ?? 1;
                    const current  = statusRank[exists.status]  ?? 1;
                    if (incoming > current) {
                        exists.status = nodeData.status;
                        added = true; // trigger re-layout / re-render
                    }
                    // Update name if incoming data has one and current doesn't, or incoming is better
                    if (nodeData.name) {
                        if (!exists.name || exists.name === 'Unknown Node' || exists.name === exists.id) {
                            exists.name = nodeData.name;
                        }
                    }
                    // Update IP if incoming data has one and current doesn't, or incoming is better
                    if (nodeData.ip) {
                        if (!exists.ip || exists.ip === '0.0.0.0' || exists.ip.startsWith('10.0.0.')) {
                            exists.ip = nodeData.ip;
                        }
                    }
                    if (nodeData.securityLevel != null && exists.securityLevel == null) {
                        exists.securityLevel = nodeData.securityLevel;
                    }
                }
            });

            if (added) {
                this.layoutNodes();
            }
            this.render();
        },

        mapNodeType(type) {
            const typeMap = {
                'personal': 'local',
                'corporate': 'server',
                'government': 'gov',
                'underground': 'relay',
                'infrastructure': 'infra',
                'financial': 'server',
                'security': 'server',
                'server': 'server',
                'database': 'database',
                'gateway': 'gateway',
                'device': 'device',
                'camera': 'camera',
                'iot': 'iot',
            };
            return typeMap[type] || 'server';
        },

        handleConnectionChanged({ connectedTo, connectedToName }) {
            console.log('[NodeManager] Connection changed:', { connectedTo, connectedToName });
            console.log('[NodeManager] Before update - currentNodeId:', this.currentNodeId);

            if (connectedTo && connectedTo !== 'localhost') {
                const node = this.nodes.find(n =>
                    n.id === connectedTo ||
                    n.ip === connectedTo ||
                    n.name === connectedToName
                );

                if (node) {
                    this.currentNodeId = node.id;
                    if (node.status !== 'owned') {
                        node.status = 'hacked';
                    }
                }
            } else {
                this.currentNodeId = 'local';
            }

            console.log('[NodeManager] After update - currentNodeId:', this.currentNodeId);
            this.render();
        },

        flashNode(node) {
            const originalStatus = node.status;
            node.status = 'scanned';
            this.render();

            setTimeout(() => {
                node.status = 'discovered';
                this.render();
            }, 300);

            setTimeout(() => {
                node.status = 'scanned';
                this.render();
            }, 600);

            setTimeout(() => {
                node.status = originalStatus;
                this.render();
            }, 900);
        },

        async loadNetworkData() {
            this.nodes = [
                {
                    id: 'local',
                    name: 'Home Terminal',
                    type: 'local',
                    x: 120,
                    y: 250,
                    status: 'owned',
                    ip: '127.0.0.1',
                    isPublic: true,
                    isDiscovered: true,
                    securityLevel: 0,
                    info: 'Home Terminal'
                }
            ];

            this.connections = [];
            this.currentNodeId = 'local';

            try {
                const response = await fetch('/api/network-state');
                const data = await response.json();
                if (data.nodes?.length) {
                    this.handleNodesDiscovered(data.nodes);
                    for (const node of data.nodes) {
                        const existing = this.nodes.find(n => n.id === node.id);
                        if (existing) {
                            // Don't downgrade a locally-known status (e.g. 'owned' → 'discovered')
                            const statusRank = { unknown: 0, discovered: 1, scanned: 2, hacked: 3, compromised: 3, owned: 4 };
                            if (node.status && (statusRank[node.status] ?? 0) >= (statusRank[existing.status] ?? 0)) {
                                existing.status = node.status;
                            }
                            if (node.isPublic !== undefined) existing.isPublic = node.isPublic;
                            if (node.isDiscovered !== undefined) existing.isDiscovered = node.isDiscovered;
                            if (node.securityLevel != null) existing.securityLevel = node.securityLevel;
                        }
                    }
                }
                if (data.connections?.length) {
                    for (const conn of data.connections) {
                        const exists = this.connections.some(c =>
                            (c.from === conn.from && c.to === conn.to) ||
                            (c.to === conn.from && c.from === conn.to)
                        );
                        if (!exists) {
                            this.connections.push({ from: conn.from, to: conn.to, style: conn.style || 'dotted' });
                        }
                    }
                }
                if (data.currentNode && data.currentNode !== 'local') {
                    this.currentNodeId = data.currentNode;
                }
                this.layoutNodes();
                this.render();
            } catch (e) {
                // Silently fail — nodes will be discovered through gameplay
            }
        },

        // --- Canvas lifecycle ---

        setupCanvas() {
            const canvas = this.$refs.canvas;
            if (!canvas) return;

            this.resizeObserver = new ResizeObserver(() => this.resizeCanvas());
            this.resizeObserver.observe(canvas.parentElement);
        },

        resizeCanvas() {
            const canvas = this.$refs.canvas;
            const container = canvas?.parentElement;
            if (!canvas || !container) return;

            canvas.width = container.clientWidth;
            canvas.height = container.clientHeight;

            // Reposition local node to left-center on resize
            const localNode = this.nodes.find(n => n.id === 'local');
            if (localNode) {
                localNode.x = 120;
                localNode.y = canvas.height / 2;
            }

            this.layoutNodes();
            this.render();
        },

        // --- Animation loop ---

        startAnimation() {
            const animate = () => {
                this.packetPhase = (this.packetPhase + 0.005) % 1;
                this.pulsePhase = (this.pulsePhase + 0.03) % (Math.PI * 2);
                this.render();
                this.animationFrame = requestAnimationFrame(animate);
            };
            this.animationFrame = requestAnimationFrame(animate);
        },

        // --- Rendering ---

        render() {
            const canvas = this.$refs.canvas;
            if (!canvas || canvas.width === 0 || canvas.height === 0) return;

            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            ctx.save();
            ctx.translate(this.viewOffset.x, this.viewOffset.y);
            ctx.scale(this.scale, this.scale);

            this.drawGrid(ctx);
            this.drawConnections(ctx);
            this.drawPackets(ctx);
            this.drawNodes(ctx);

            ctx.restore();
        },

        drawGrid(ctx) {
            const canvas = this.$refs.canvas;
            const spacing = 40;

            // Compute visible area in world coords
            const left = -this.viewOffset.x / this.scale;
            const top = -this.viewOffset.y / this.scale;
            const right = left + canvas.width / this.scale;
            const bottom = top + canvas.height / this.scale;

            ctx.strokeStyle = 'rgba(42, 45, 54, 0.5)';
            ctx.lineWidth = 0.5;
            ctx.setLineDash([1, 3]);

            const startX = Math.floor(left / spacing) * spacing;
            const startY = Math.floor(top / spacing) * spacing;

            for (let x = startX; x <= right; x += spacing) {
                ctx.beginPath();
                ctx.moveTo(x, top);
                ctx.lineTo(x, bottom);
                ctx.stroke();
            }

            for (let y = startY; y <= bottom; y += spacing) {
                ctx.beginPath();
                ctx.moveTo(left, y);
                ctx.lineTo(right, y);
                ctx.stroke();
            }

            ctx.setLineDash([]);
        },

        drawConnections(ctx) {
            // 4d: Compute active path for highlight
            const activePath = this.getActivePath();

            for (const conn of this.connections) {
                const fromNode = this.nodes.find(n => n.id === conn.from);
                const toNode = this.nodes.find(n => n.id === conn.to);
                if (!fromNode || !toNode) continue;

                const isSolid = conn.style === 'solid' ||
                    (this.isNodeAccessible(fromNode) && this.isNodeAccessible(toNode));
                const isDotted = conn.style === 'dotted' && !isSolid;

                // 4d: Is this connection on the active bounce chain?
                const isOnActivePath = activePath.has(conn.from) && activePath.has(conn.to);

                ctx.beginPath();
                if (isSolid) {
                    // 4d: Brighter glow for active path connections
                    if (isOnActivePath) {
                        ctx.strokeStyle = 'rgba(34, 211, 238, 0.5)';
                        ctx.lineWidth = 10;
                        ctx.setLineDash([]);
                        ctx.moveTo(fromNode.x, fromNode.y);
                        ctx.lineTo(toNode.x, toNode.y);
                        ctx.stroke();
                        ctx.beginPath();
                    }

                    // Glow layer
                    ctx.strokeStyle = 'rgba(8, 145, 178, 0.3)';
                    ctx.lineWidth = 6;
                    ctx.setLineDash([]);
                    ctx.moveTo(fromNode.x, fromNode.y);
                    ctx.lineTo(toNode.x, toNode.y);
                    ctx.stroke();

                    // Main line
                    ctx.beginPath();
                    ctx.strokeStyle = '#0891b2';
                    ctx.lineWidth = 2;
                    ctx.setLineDash([]);
                    ctx.moveTo(fromNode.x, fromNode.y);
                    ctx.lineTo(toNode.x, toNode.y);
                    ctx.stroke();
                } else if (isDotted) {
                    // Amber dotted line — discovered but unbreached route
                    ctx.strokeStyle = 'rgba(251, 191, 36, 0.5)';
                    ctx.lineWidth = 1.5;
                    ctx.setLineDash([4, 4]);
                    ctx.moveTo(fromNode.x, fromNode.y);
                    ctx.lineTo(toNode.x, toNode.y);
                    ctx.stroke();
                    ctx.setLineDash([]);
                } else {
                    // Unknown/undiscovered connection
                    ctx.strokeStyle = '#2a2d36';
                    ctx.lineWidth = 1.5;
                    ctx.setLineDash([5, 5]);
                    ctx.moveTo(fromNode.x, fromNode.y);
                    ctx.lineTo(toNode.x, toNode.y);
                    ctx.stroke();
                    ctx.setLineDash([]);
                }

                // Arrowhead at destination
                this.drawArrowhead(ctx, fromNode, toNode, isSolid);
            }
        },

        drawArrowhead(ctx, fromNode, toNode, isActive) {
            const radius = 28;
            const dx = toNode.x - fromNode.x;
            const dy = toNode.y - fromNode.y;
            const len = Math.sqrt(dx * dx + dy * dy);
            if (len === 0) return;

            const ux = dx / len;
            const uy = dy / len;

            // Arrow tip sits just outside the destination node
            const tipX = toNode.x - ux * (radius + 4);
            const tipY = toNode.y - uy * (radius + 4);

            const arrowLen = 10;
            const arrowWidth = 5;

            const baseX = tipX - ux * arrowLen;
            const baseY = tipY - uy * arrowLen;

            ctx.beginPath();
            ctx.moveTo(tipX, tipY);
            ctx.lineTo(baseX + uy * arrowWidth, baseY - ux * arrowWidth);
            ctx.lineTo(baseX - uy * arrowWidth, baseY + ux * arrowWidth);
            ctx.closePath();
            ctx.fillStyle = isActive ? '#0891b2' : '#2a2d36';
            ctx.fill();
        },

        drawPackets(ctx) {
            for (const conn of this.connections) {
                const fromNode = this.nodes.find(n => n.id === conn.from);
                const toNode = this.nodes.find(n => n.id === conn.to);
                if (!fromNode || !toNode) continue;

                // No animated packets on dotted (unbreached) connections
                if (conn.style === 'dotted') continue;

                const isActive = this.isNodeAccessible(fromNode) && this.isNodeAccessible(toNode);
                if (!isActive) continue;

                // Draw multiple packets at different phases
                const packetCount = 3;
                for (let i = 0; i < packetCount; i++) {
                    const phase = (this.packetPhase + i / packetCount) % 1;
                    const px = fromNode.x + (toNode.x - fromNode.x) * phase;
                    const py = fromNode.y + (toNode.y - fromNode.y) * phase;

                    ctx.fillStyle = '#22d3ee';
                    ctx.shadowColor = '#22d3ee';
                    ctx.shadowBlur = 4;
                    ctx.fillRect(px - 1.5, py - 1.5, 3, 3);
                    ctx.shadowBlur = 0;
                }
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
            const radius = 28;

            const colors = {
                owned: { fill: '#22d3ee', stroke: '#0891b2' },
                hacked: { fill: '#4ade80', stroke: '#16a34a' },
                compromised: { fill: '#4ade80', stroke: '#16a34a' },
                scanned: { fill: '#fbbf24', stroke: '#d97706' },
                discovered: { fill: '#a1a1aa', stroke: '#71717a' },
                locked: { fill: '#f87171', stroke: '#dc2626' },
                unknown: { fill: '#52525b', stroke: '#3f3f46' }
            };

            const color = colors[node.status] || colors.unknown;

            // 4b: Broadcast rings for public gateway nodes
            if (node.isPublic && node.id !== 'local') {
                const ringPhase = this.pulsePhase * 0.5;
                for (let i = 0; i < 3; i++) {
                    const ringOffset = ((ringPhase / (Math.PI * 2) + i / 3) % 1);
                    const ringRadius = radius + 10 + ringOffset * 20;
                    const ringAlpha = (1 - ringOffset) * 0.25;
                    ctx.beginPath();
                    ctx.arc(node.x, node.y, ringRadius, 0, Math.PI * 2);
                    ctx.strokeStyle = `rgba(34, 211, 238, ${ringAlpha})`;
                    ctx.lineWidth = 1;
                    ctx.setLineDash([]);
                    ctx.stroke();
                }
            }

            // Pulsing ring on current node
            if (isCurrent) {
                const pulseRadius = radius + 8 + Math.sin(this.pulsePhase) * 4;
                const pulseAlpha = 0.15 + Math.sin(this.pulsePhase) * 0.1;
                ctx.beginPath();
                ctx.arc(node.x, node.y, pulseRadius, 0, Math.PI * 2);
                ctx.strokeStyle = `rgba(34, 211, 238, ${pulseAlpha + 0.15})`;
                ctx.lineWidth = 2;
                ctx.stroke();

                // Subtle glow fill
                ctx.beginPath();
                ctx.arc(node.x, node.y, pulseRadius, 0, Math.PI * 2);
                ctx.fillStyle = `rgba(34, 211, 238, ${pulseAlpha * 0.5})`;
                ctx.fill();
            }

            // Selection ring
            if (isSelected) {
                ctx.beginPath();
                ctx.arc(node.x, node.y, radius + 5, 0, Math.PI * 2);
                ctx.strokeStyle = '#22d3ee';
                ctx.lineWidth = 2;
                ctx.setLineDash([4, 4]);
                ctx.stroke();
                ctx.setLineDash([]);
            }

            // Node circle
            ctx.beginPath();
            ctx.arc(node.x, node.y, radius, 0, Math.PI * 2);
            ctx.fillStyle = color.fill;
            ctx.fill();
            ctx.strokeStyle = color.stroke;
            ctx.lineWidth = 2;
            ctx.stroke();

            // Type icon
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
                iot: 'IoT',
                gov: 'GOV',
                relay: 'R',
                infra: 'INF'
            };
            ctx.fillText(icons[node.type] || '?', node.x, node.y);

            // Name: visible only after connect (or if node is public)
            const nameRevealed = ['compromised', 'hacked', 'owned'].includes(node.status) || node.isPublic;
            // IP: visible after targeted scan (or if node is public)
            const ipRevealed   = ['scanned', 'compromised', 'hacked', 'owned'].includes(node.status) || node.isPublic;

            ctx.font = '11px Inter, system-ui, sans-serif';
            ctx.fillStyle = nameRevealed ? '#e4e4e7' : '#52525b';
            const displayName = nameRevealed
                ? (node.name && node.name !== 'undefined' ? node.name : 'Unknown')
                : '???';
            ctx.fillText(displayName, node.x, node.y + radius + 15);

            if (node.status !== 'unknown') {
                ctx.fillStyle = '#52525b';
                ctx.font = '9px "JetBrains Mono"';
                const displayIp = ipRevealed
                    ? (node.ip && node.ip !== 'undefined' ? node.ip : '—')
                    : '???';
                ctx.fillText(displayIp, node.x, node.y + radius + 28);
            }
        },

        isNodeAccessible(node) {
            return ['owned', 'hacked', 'scanned'].includes(node.status);
        },

        // --- Interaction ---

        handleCanvasClick(event) {
            const canvas = this.$refs.canvas;
            const rect = canvas.getBoundingClientRect();
            const x = (event.clientX - rect.left - this.viewOffset.x) / this.scale;
            const y = (event.clientY - rect.top - this.viewOffset.y) / this.scale;

            for (const node of this.nodes) {
                const dist = Math.sqrt((x - node.x) ** 2 + (y - node.y) ** 2);
                if (dist <= 28) {
                    this.selectedNode = node;
                    return;
                }
            }

            this.selectedNode = null;
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
            }
        },

        handleCanvasMouseUp() {
            this.isDragging = false;
        },

        handleWheel(event) {
            event.preventDefault();
            const delta = event.deltaY > 0 ? 0.9 : 1.1;
            this.scale = Math.max(0.5, Math.min(2, this.scale * delta));
        },

        getNodeAtPosition(event) {
            const canvas = this.$refs.canvas;
            const rect = canvas.getBoundingClientRect();
            const x = (event.clientX - rect.left - this.viewOffset.x) / this.scale;
            const y = (event.clientY - rect.top - this.viewOffset.y) / this.scale;

            for (const node of this.nodes) {
                const dist = Math.sqrt((x - node.x) ** 2 + (y - node.y) ** 2);
                if (dist <= 28) return node;
            }
            return null;
        },

        // --- Info panel helpers ---

        getStatusLabel(status) {
            const labels = {
                owned: 'OWNED',
                hacked: 'COMPROMISED',
                compromised: 'COMPROMISED',
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
                compromised: 'text-green-400',
                scanned: 'text-yellow-400',
                discovered: 'os-text-dim',
                locked: 'text-red-400',
                unknown: 'os-text-muted'
            };
            return colors[status] || 'os-text-muted';
        },

        generateIpFromGateway(nodeId) {
            const parentMap = this.getParentMap();
            let current = nodeId;
            let gateway = null;
            for (let i = 0; i < 10; i++) {
                const parent = parentMap[current];
                if (!parent) break;
                const parentNode = this.nodes.find(n => n.id === parent);
                if (parentNode?.isPublic && parentNode.ip) { gateway = parentNode; break; }
                current = parent;
            }
            const base = gateway?.ip;
            if (base) {
                const parts = base.split('.');
                if (parts.length === 4) {
                    return `${parts[0]}.${parts[1]}.${parts[2]}.${Math.floor(Math.random() * 200) + 10}`;
                }
            }
            return '10.0.0.' + (Math.floor(Math.random() * 200) + 10);
        },

        centerView() {
            this.viewOffset = { x: 0, y: 0 };
            this.scale = 1;
        },

        async executeDisconnect() {
            if (this.isDisconnecting) return;

            this.isDisconnecting = true;

            try {
                const response = await fetch('/api/terminal', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({
                        command: 'disconnect',
                        context: {
                            currentPath: '/',
                            connectedTo: this.currentNodeId
                        }
                    }),
                });

                const result = await response.json();

                // Terminal handles state changes and dispatches 'terminal:connection-changed'
                // Node Manager already listens for that event (line 46-48)
                // So no manual state update needed here

                if (!result.success) {
                    console.error('[NodeManager] Disconnect failed:', result.output);
                }
            } catch (error) {
                console.error('[NodeManager] Disconnect error:', error);
            } finally {
                // Reset flag after brief delay to allow event propagation
                setTimeout(() => {
                    this.isDisconnecting = false;
                }, 500);
            }
        }
    };
}
