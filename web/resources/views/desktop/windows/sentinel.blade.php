<div x-data="sentinel()" x-init="init()" @destroy="destroy()" class="h-full flex flex-col os-window">
    {{-- Header --}}
    <div class="flex items-center justify-between px-3 py-2 border-b" style="border-color: #2a2d36; background-color: #1a1c23;">
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2">
                <span class="text-lg">🛡️</span>
                <span class="text-sm font-medium os-text">SENTINEL</span>
                <span class="text-xs os-text-muted">v2.1</span>
            </div>
            {{-- Status indicator --}}
            <div
                x-show="status"
                class="px-2 py-0.5 rounded text-xs font-mono font-medium"
                :class="{
                    'bg-green-500/20 text-green-400': status?.status === 'SECURE',
                    'bg-cyan-500/20 text-cyan-400': status?.status === 'MONITORING',
                    'bg-yellow-500/20 text-yellow-400': status?.status === 'ELEVATED THREAT',
                    'bg-red-500/20 text-red-400 animate-pulse': status?.status === 'UNDER ATTACK'
                }"
                x-text="status?.status"
            ></div>
        </div>

        {{-- Threat count badge --}}
        <div class="flex items-center gap-2">
            <div
                x-show="threats.length > 0"
                class="px-2 py-0.5 rounded text-xs font-medium bg-red-500/20 text-red-400 animate-pulse"
            >
                <span x-text="threats.length"></span> ACTIVE THREAT<span x-show="threats.length > 1">S</span>
            </div>
            <button
                @click="loadStatus(); loadEvents()"
                class="p-1.5 rounded os-bg-hover os-text-dim transition-colors"
                :class="isLoading ? 'animate-spin' : ''"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Main Content --}}
    <div class="flex-1 flex overflow-hidden">
        {{-- Left Panel - Status & Connections --}}
        <div class="flex-1 flex flex-col overflow-hidden">
            {{-- Exposure & Firewall Meters --}}
            <div class="p-3 border-b grid grid-cols-2 gap-4" style="border-color: #2a2d36;">
                {{-- Exposure Meter --}}
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs os-text-muted">EXPOSURE</span>
                        <span class="text-xs font-mono" :class="getExposureColor()" x-text="(status?.exposure || 0) + '%'"></span>
                    </div>
                    <div class="h-2 rounded-full overflow-hidden" style="background-color: #0c0d10;">
                        <div
                            class="h-full transition-all duration-500"
                            :class="getExposureBarColor()"
                            :style="'width: ' + (status?.exposure || 0) + '%'"
                        ></div>
                    </div>
                    <div class="text-xs mt-1" :class="getExposureColor()" x-text="status?.exposureLevel?.label || 'Unknown'"></div>
                </div>

                {{-- Firewall Strength --}}
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs os-text-muted">FIREWALL</span>
                        <span class="text-xs font-mono text-cyan-400" x-text="(status?.firewallStrength || 0) + '%'"></span>
                    </div>
                    <div class="h-2 rounded-full overflow-hidden" style="background-color: #0c0d10;">
                        <div
                            class="h-full bg-cyan-500 transition-all duration-500"
                            :style="'width: ' + (status?.firewallStrength || 0) + '%'"
                        ></div>
                    </div>
                    <div class="text-xs mt-1 text-cyan-400">Active Protection</div>
                </div>
            </div>

            {{-- Shield Banner --}}
            <div
                x-show="status?.shield?.active"
                class="px-3 py-2 border-b flex items-center justify-between"
                style="border-color: #2a2d36; background-color: rgba(34, 197, 94, 0.08);"
            >
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    <span class="text-xs font-medium text-green-400">SHIELD ACTIVE</span>
                    <span class="text-xs text-green-400/70">Exposure frozen</span>
                </div>
                <span class="text-xs font-mono text-green-400" x-text="getShieldTimeFormatted()"></span>
            </div>

            {{-- Tabs --}}
            <div class="flex border-b" style="border-color: #2a2d36;">
                <button
                    @click="activeTab = 'monitor'"
                    class="px-4 py-2 text-sm transition-colors"
                    :class="activeTab === 'monitor' ? 'os-accent border-b-2 border-cyan-500' : 'os-text-dim os-bg-hover'"
                >Connections</button>
                <button
                    @click="activeTab = 'log'"
                    class="px-4 py-2 text-sm transition-colors"
                    :class="activeTab === 'log' ? 'os-accent border-b-2 border-cyan-500' : 'os-text-dim os-bg-hover'"
                >Event Log</button>
            </div>

            {{-- Connections List --}}
            <div x-show="activeTab === 'monitor'" class="flex-1 overflow-y-auto">
                {{-- Loading --}}
                <div x-show="isLoading" class="flex items-center justify-center h-32">
                    <div class="text-sm os-text-dim">Scanning network...</div>
                </div>

                {{-- Empty state --}}
                <div x-show="!isLoading && connections.length === 0" class="flex items-center justify-center h-32">
                    <div class="text-center">
                        <div class="text-2xl mb-2">✓</div>
                        <div class="text-sm os-text-dim">No incoming connections</div>
                    </div>
                </div>

                {{-- Connection items --}}
                <template x-for="conn in connections" :key="conn.id">
                    <button
                        @click="selectConnection(conn)"
                        class="w-full px-3 py-2 flex items-center gap-3 border-b transition-colors text-left"
                        :class="selectedConnection?.id === conn.id ? 'os-bg-active' : 'os-bg-hover'"
                        style="border-color: #2a2d36;"
                    >
                        {{-- Threat indicator --}}
                        <span class="text-lg" x-text="getThreatIcon(conn.threatLevel)"></span>

                        {{-- Connection info --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-sm os-text" x-text="conn.sourceIp"></span>
                                <span
                                    class="px-1.5 py-0.5 rounded text-xs"
                                    :class="{
                                        'bg-red-500/20 text-red-400': conn.type === 'intrusion',
                                        'bg-yellow-500/20 text-yellow-400': conn.type === 'scan',
                                        'bg-cyan-500/20 text-cyan-400': conn.type === 'probe'
                                    }"
                                    x-text="getTypeLabel(conn.type)"
                                ></span>
                                {{-- Counter-hack active indicator --}}
                                <span
                                    x-show="counterHackConnectionId === conn.id"
                                    class="px-1.5 py-0.5 rounded text-xs bg-orange-500/20 text-orange-400"
                                >HACKING</span>
                            </div>
                            <div class="text-xs os-text-muted" x-text="conn.sourceNode !== 'unknown' ? conn.sourceNode : 'Unknown source'"></div>
                        </div>

                        {{-- Time --}}
                        <div class="text-xs os-text-muted" x-text="conn.timeAgo"></div>

                        {{-- Progress bar for active intrusions --}}
                        <template x-if="conn.progress !== undefined">
                            <div class="w-16">
                                <div class="h-1 rounded-full overflow-hidden" style="background-color: #0c0d10;">
                                    <div
                                        class="h-full bg-red-500 transition-all"
                                        :style="'width: ' + conn.progress + '%'"
                                    ></div>
                                </div>
                                <div class="text-xs text-red-400 text-right" x-text="conn.timeRemaining + 's'"></div>
                            </div>
                        </template>
                    </button>
                </template>
            </div>

            {{-- Event Log --}}
            <div x-show="activeTab === 'log'" class="flex-1 overflow-y-auto">
                <template x-for="event in events" :key="event.id">
                    <div
                        class="px-3 py-2 border-b flex items-start gap-2"
                        style="border-color: #2a2d36;"
                    >
                        <span x-text="getEventIcon(event.type)"></span>
                        <div class="flex-1">
                            <div class="text-sm" :class="getSeverityClass(event.severity)" x-text="event.message"></div>
                            <div class="text-xs os-text-muted" x-text="event.timeAgo"></div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Right Panel - Selected Connection Details --}}
        <div
            x-show="selectedConnection"
            class="w-64 border-l overflow-y-auto flex-shrink-0"
            style="border-color: #2a2d36; background-color: #13151a;"
        >
            <template x-if="selectedConnection">
                <div class="p-3">
                    {{-- Header --}}
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-lg" x-text="getThreatIcon(selectedConnection.threatLevel)"></span>
                        <button @click="selectedConnection = null" class="os-text-dim os-bg-hover p-1 rounded">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>

                    {{-- Connection Details --}}
                    <div class="space-y-3 mb-4">
                        <div>
                            <div class="text-xs os-text-muted">SOURCE IP</div>
                            <div class="font-mono text-sm os-text" x-text="selectedConnection.sourceIp"></div>
                        </div>
                        <div>
                            <div class="text-xs os-text-muted">NODE</div>
                            <div class="text-sm os-text" x-text="selectedConnection.sourceNode || 'Unknown'"></div>
                        </div>
                        <div>
                            <div class="text-xs os-text-muted">TYPE</div>
                            <div class="text-sm" :class="{
                                'text-red-400': selectedConnection.type === 'intrusion',
                                'text-yellow-400': selectedConnection.type === 'scan',
                                'text-cyan-400': selectedConnection.type === 'probe'
                            }" x-text="getTypeLabel(selectedConnection.type)"></div>
                        </div>
                        <div x-show="selectedConnection.port">
                            <div class="text-xs os-text-muted">PORT</div>
                            <div class="font-mono text-sm os-text" x-text="selectedConnection.port"></div>
                        </div>
                        <div>
                            <div class="text-xs os-text-muted">DETECTED</div>
                            <div class="text-sm os-text" x-text="selectedConnection.timeAgo"></div>
                        </div>
                    </div>

                    {{-- Trace Info (if traced) --}}
                    <template x-if="selectedConnection.traced && selectedConnection.traceInfo">
                        <div class="mb-4 p-2 rounded border" style="background-color: #0c0d10; border-color: #2a2d36;">
                            <div class="text-xs os-accent mb-2">TRACE RESULTS</div>
                            <div class="text-xs os-text-dim space-y-1">
                                <div>Node: <span class="os-text" x-text="selectedConnection.traceInfo.nodeName"></span></div>
                                <div>Type: <span class="os-text" x-text="selectedConnection.traceInfo.nodeType"></span></div>
                                <div>Org: <span class="os-text" x-text="selectedConnection.traceInfo.organization"></span></div>
                            </div>
                        </div>
                    </template>

                    {{-- Counter-Hack Puzzle (replaces button when active) --}}
                    <template x-if="counterHackConnectionId === selectedConnection.id && counterHackChallenge">
                        <div class="mb-4 p-2 rounded border" style="background-color: #0c0d10; border-color: #f97316;">
                            <div class="text-xs text-orange-400 mb-2 font-medium">COUNTER-HACK ACTIVE</div>
                            <div class="text-xs font-mono os-text mb-2" x-text="counterHackChallenge"></div>
                            <div class="text-xs text-orange-400/70">Type <span class="font-mono">solve &lt;answer&gt;</span> in Terminal</div>
                        </div>
                    </template>

                    {{-- Actions --}}
                    <div class="space-y-2">
                        <button
                            @click="blockConnection(selectedConnection.id)"
                            :disabled="actionInProgress"
                            class="w-full px-3 py-2 rounded text-sm font-medium transition-colors flex items-center justify-center gap-2"
                            :class="actionInProgress ? 'bg-gray-700 text-gray-500' : 'bg-cyan-600 hover:bg-cyan-500 text-white'"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                            </svg>
                            Block Connection
                        </button>

                        <button
                            @click="traceConnection(selectedConnection.id)"
                            :disabled="actionInProgress || selectedConnection.traced"
                            class="w-full px-3 py-2 rounded text-sm font-medium transition-colors flex items-center justify-center gap-2 border"
                            :class="actionInProgress || selectedConnection.traced ? 'border-gray-700 text-gray-500' : 'border-cyan-700 text-cyan-400 hover:bg-cyan-500/10'"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <span x-text="selectedConnection.traced ? 'Traced' : 'Trace Source'"></span>
                        </button>

                        <button
                            @click="counterHack(selectedConnection.id)"
                            :disabled="actionInProgress"
                            x-show="selectedConnection.type === 'intrusion' && counterHackConnectionId !== selectedConnection.id"
                            class="w-full px-3 py-2 rounded text-sm font-medium transition-colors flex items-center justify-center gap-2"
                            :class="actionInProgress ? 'bg-gray-700 text-gray-500' : 'bg-red-600 hover:bg-red-500 text-white'"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            Counter-Hack
                        </button>
                    </div>

                    {{-- Counter-hack instruction --}}
                    <div x-show="selectedConnection.type === 'intrusion' && counterHackConnectionId !== selectedConnection.id" class="mt-3 p-2 rounded text-xs" style="background-color: #0c0d10;">
                        <span class="text-yellow-400">Info:</span>
                        <span class="os-text-dim">Initiates a puzzle challenge. Solve it in the terminal for a temporary shield.</span>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- Footer Status Bar --}}
    <div class="px-3 py-1.5 border-t flex items-center justify-between text-xs" style="border-color: #2a2d36; background-color: #0c0d10;">
        <div class="os-text-muted">
            Last scan: <span x-text="status?.lastScan || 'Never'"></span>
        </div>
        <div class="os-text-muted">
            Uptime: <span x-text="status?.uptime || '--'"></span>
        </div>
    </div>
</div>
