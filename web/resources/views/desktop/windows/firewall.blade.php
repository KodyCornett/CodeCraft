<div x-data="firewall()" x-init="init()" @destroy="destroy()" class="h-full flex flex-col os-window">
    {{-- Header --}}
    <div class="flex items-center justify-between px-3 py-2 border-b" style="border-color: #2a2d36; background-color: #1a1c23;">
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 os-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
                <span class="text-sm font-medium os-text">FIREWALL</span>
            </div>
            <div
                class="px-2 py-0.5 rounded text-xs font-mono font-medium border"
                :class="getStatusBgColor()"
            >
                <span :class="getStatusColor()" x-text="getStatusLabel()"></span>
            </div>
        </div>
    </div>

    {{-- Main Content --}}
    <div class="flex-1 flex flex-col items-center justify-center p-6 gap-6">
        {{-- Shield Icon --}}
        <div class="relative">
            <svg
                class="w-24 h-24 transition-colors duration-500"
                :class="{
                    'text-green-400': status === 'active',
                    'text-red-400 animate-pulse': status === 'damaged',
                    'text-yellow-400': status === 'repairing',
                    'text-cyan-400': status === 'repaired'
                }"
                fill="none" stroke="currentColor" viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            {{-- Broken overlay for damaged --}}
            <template x-if="status === 'damaged'">
                <svg class="w-24 h-24 text-red-500 absolute inset-0 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </template>
        </div>

        {{-- Status Label --}}
        <div class="text-center">
            <div class="text-lg font-mono font-medium" :class="getStatusColor()" x-text="getStatusLabel()"></div>
            <div class="text-sm os-text-dim mt-1">
                <template x-if="status === 'active'">
                    <span>Packet filter engine running. All systems protected.</span>
                </template>
                <template x-if="status === 'damaged'">
                    <span>Firewall integrity compromised. Repair required.</span>
                </template>
                <template x-if="status === 'repairing'">
                    <span>Rebuilding packet filter rules...</span>
                </template>
                <template x-if="status === 'repaired'">
                    <span>Repair complete. Activate to restore protection.</span>
                </template>
            </div>
        </div>

        {{-- Progress Bar (repairing only) --}}
        <template x-if="status === 'repairing'">
            <div class="w-full max-w-xs">
                <div class="h-2 rounded-full overflow-hidden" style="background-color: #1a1c23;">
                    <div
                        class="h-full rounded-full bg-yellow-400 transition-all duration-100"
                        :style="`width: ${repairProgress}%`"
                    ></div>
                </div>
                <div class="text-xs os-text-muted text-center mt-1 font-mono" x-text="`${repairProgress}%`"></div>
            </div>
        </template>

        {{-- Action Button --}}
        <template x-if="status === 'damaged'">
            <button
                @click="startRepair()"
                class="px-6 py-2 rounded font-medium text-sm transition-colors bg-red-500/20 text-red-400 border border-red-500/30 hover:bg-red-500/30"
            >
                Run Repair
            </button>
        </template>
        <template x-if="status === 'repaired'">
            <button
                @click="activateFirewall()"
                class="px-6 py-2 rounded font-medium text-sm transition-colors bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 hover:bg-cyan-500/30"
            >
                Activate Firewall
            </button>
        </template>

        {{-- DEFRAG Section (visible when active and traces exist) --}}
        <template x-if="canShowDefrag()">
            <div class="w-full max-w-sm p-4 rounded border" style="background-color: #0c0d10; border-color: #2a2d36;">
                <div class="flex items-center gap-2 mb-3">
                    <svg class="w-4 h-4 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                    <span class="text-sm font-medium text-yellow-400">CONNECTION TRACES DETECTED</span>
                </div>

                <div class="flex items-center gap-2 mb-3">
                    <div class="text-xs os-text-dim">Active traces:</div>
                    <div class="px-2 py-0.5 rounded text-xs font-mono font-medium bg-yellow-500/20 text-yellow-400" x-text="traceCount"></div>
                </div>

                <div class="text-xs os-text-dim mb-3">
                    Connection traces leave a digital footprint. Run DEFRAG to purge them and reduce your exposure.
                </div>

                <template x-if="defragActive">
                    <div class="text-xs font-mono text-yellow-400">DEFRAG in progress — solve puzzles in Terminal</div>
                </template>

                <template x-if="!defragActive && canRunDefrag()">
                    <div class="text-xs os-text">
                        Type <span class="font-mono text-cyan-400">defrag</span> in Terminal to start.
                    </div>
                </template>

                <template x-if="!defragActive && !isOnLocalhost">
                    <div class="text-xs text-red-400">
                        Disconnect from remote node first. DEFRAG requires localhost.
                    </div>
                </template>
            </div>
        </template>
    </div>

    {{-- Footer --}}
    <div class="px-3 py-2 border-t flex items-center justify-between" style="border-color: #2a2d36; background-color: #0c0d10;">
        <span class="text-xs os-text-muted font-mono">Packet filter engine</span>
        <span class="text-xs os-text-muted font-mono">
            <template x-if="traceCount > 0">
                <span class="text-yellow-400" x-text="traceCount + ' trace(s)'"></span>
            </template>
            <template x-if="traceCount === 0">
                <span>247 rules loaded</span>
            </template>
        </span>
    </div>
</div>
