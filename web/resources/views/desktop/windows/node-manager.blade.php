<div x-data="nodeManager()" class="h-full flex os-window">
    {{-- Canvas Area --}}
    <div class="flex-1 relative overflow-hidden" style="background-color: #0a0b0d;">
        <canvas
            x-ref="canvas"
            class="w-full h-full cursor-grab"
            :class="{ 'cursor-grabbing': isDragging }"
            @click="handleCanvasClick($event)"
            @mousedown="handleCanvasMouseDown($event)"
            @mousemove="handleCanvasMouseMove($event)"
            @mouseup="handleCanvasMouseUp()"
            @mouseleave="handleCanvasMouseUp()"
            @wheel="handleWheel($event)"
        ></canvas>

        {{-- Legend --}}
        <div class="absolute top-3 left-3 p-3 rounded-lg text-xs" style="background-color: rgba(19, 21, 26, 0.9); border: 1px solid #2a2d36;">
            <div class="font-medium os-text mb-2">Node Status</div>
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full" style="background-color: #22d3ee;"></div>
                    <span class="os-text-dim">Owned</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full" style="background-color: #4ade80;"></div>
                    <span class="os-text-dim">Compromised</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full" style="background-color: #fbbf24;"></div>
                    <span class="os-text-dim">Scanned</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full" style="background-color: #a1a1aa;"></div>
                    <span class="os-text-dim">Discovered</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full" style="background-color: #f87171;"></div>
                    <span class="os-text-dim">Locked</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full" style="background-color: #52525b;"></div>
                    <span class="os-text-dim">Unknown</span>
                </div>
            </div>
        </div>

        {{-- Controls --}}
        <div class="absolute bottom-3 left-3 flex gap-2">
            <button
                @click="centerView()"
                class="px-3 py-1.5 rounded text-xs font-medium os-text-dim os-bg-hover border transition-colors"
                style="border-color: #2a2d36; background-color: rgba(19, 21, 26, 0.9);"
            >
                Reset View
            </button>
        </div>

        {{-- Current Location Indicator --}}
        <div class="absolute top-3 right-3 px-3 py-2 rounded-lg text-xs" style="background-color: rgba(19, 21, 26, 0.9); border: 1px solid #0891b2;">
            <div class="os-text-muted mb-1">Current Position</div>
            <div class="os-accent font-mono font-medium" x-text="nodes.find(n => n.id === currentNodeId)?.name || 'Unknown'"></div>
        </div>
    </div>

    {{-- Info Panel (right side) --}}
    <div class="w-64 border-l flex flex-col" style="border-color: #2a2d36; background-color: #13151a;">
        {{-- Panel Header --}}
        <div class="px-4 py-3 border-b" style="border-color: #2a2d36; background-color: #1a1c23;">
            <div class="text-sm font-medium os-text">Node Details</div>
        </div>

        {{-- Node Info --}}
        <div class="flex-1 p-4 overflow-y-auto">
            <template x-if="selectedNode">
                <div class="space-y-4">
                    {{-- Node Name --}}
                    <div>
                        <div class="text-xs os-text-muted mb-1">Name</div>
                        <div class="text-sm font-medium os-text" x-text="selectedNode.name"></div>
                    </div>

                    {{-- Status --}}
                    <div>
                        <div class="text-xs os-text-muted mb-1">Status</div>
                        <div class="text-sm font-medium" :class="getStatusColor(selectedNode.status)" x-text="getStatusLabel(selectedNode.status)"></div>
                    </div>

                    {{-- IP Address --}}
                    <div>
                        <div class="text-xs os-text-muted mb-1">IP Address</div>
                        <div class="text-sm font-mono os-text-dim" x-text="selectedNode.ip"></div>
                    </div>

                    {{-- Type --}}
                    <div>
                        <div class="text-xs os-text-muted mb-1">Type</div>
                        <div class="text-sm os-text-dim capitalize" x-text="selectedNode.type"></div>
                    </div>

                    {{-- Info --}}
                    <div>
                        <div class="text-xs os-text-muted mb-1">Info</div>
                        <div class="text-sm os-text-dim" x-text="selectedNode.info"></div>
                    </div>

                    {{-- Connections --}}
                    <div>
                        <div class="text-xs os-text-muted mb-2">Connected To</div>
                        <div class="space-y-1">
                            <template x-for="conn in connections.filter(c => c.from === selectedNode.id || c.to === selectedNode.id)" :key="conn.from + conn.to">
                                <div class="text-xs os-text-dim font-mono px-2 py-1 rounded" style="background-color: #0c0d10;">
                                    <span x-text="nodes.find(n => n.id === (conn.from === selectedNode.id ? conn.to : conn.from))?.name || 'Unknown'"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="pt-2 border-t" style="border-color: #2a2d36;">
                        <div class="text-xs os-text-muted mb-2">Actions</div>
                        <div class="space-y-2">
                            <template x-if="selectedNode.status === 'scanned' || selectedNode.status === 'discovered'">
                                <button class="w-full px-3 py-1.5 rounded text-xs font-medium os-accent border border-cyan-700 os-bg-hover transition-colors">
                                    Connect
                                </button>
                            </template>
                            <template x-if="selectedNode.status === 'hacked'">
                                <button class="w-full px-3 py-1.5 rounded text-xs font-medium text-green-400 border border-green-700 os-bg-hover transition-colors">
                                    Access Terminal
                                </button>
                            </template>
                            <template x-if="selectedNode.id === currentNodeId">
                                <button class="w-full px-3 py-1.5 rounded text-xs font-medium os-text-dim border os-bg-hover transition-colors" style="border-color: #2a2d36;">
                                    Scan Network
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </template>

            <template x-if="!selectedNode">
                <div class="flex flex-col items-center justify-center h-full text-center">
                    <svg class="w-12 h-12 os-text-muted mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>
                    </svg>
                    <div class="text-sm os-text-muted">Select a node to view details</div>
                    <div class="text-xs os-text-muted mt-1">Click and drag to pan, scroll to zoom</div>
                </div>
            </template>
        </div>

        {{-- Stats Footer --}}
        <div class="px-4 py-3 border-t text-xs" style="border-color: #2a2d36; background-color: #0c0d10;">
            <div class="flex justify-between os-text-muted">
                <span>Nodes:</span>
                <span class="os-text-dim" x-text="nodes.length"></span>
            </div>
            <div class="flex justify-between os-text-muted mt-1">
                <span>Compromised:</span>
                <span class="text-green-400" x-text="nodes.filter(n => n.status === 'hacked' || n.status === 'owned').length"></span>
            </div>
        </div>
    </div>
</div>
