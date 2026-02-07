<div x-data="{
    systemInfo: {
        hostname: 'localhost',
        os: 'CodeCraft OS v0.1',
        kernel: '5.15.0-generic',
        uptime: '3 days, 14 hours',
        ip: '127.0.0.1',
        mac: '00:1A:2B:3C:4D:5E'
    },
    resources: {
        cpu: 23,
        memory: 45,
        disk: 62,
        network: 12
    },
    tools: [
        { name: 'Terminal', icon: 'terminal', installed: true },
        { name: 'Node Manager', icon: 'network', installed: true },
        { name: 'File Browser', icon: 'folder', installed: true },
        { name: 'Port Scanner', icon: 'scan', installed: true },
        { name: 'Packet Sniffer', icon: 'packet', installed: false },
        { name: 'Exploit Kit', icon: 'exploit', installed: false }
    ]
}" class="h-full flex flex-col os-window">

    {{-- System Info Header --}}
    <div class="px-4 py-3 border-b flex items-center gap-4" style="border-color: #2a2d36; background-color: #1a1c23;">
        <div class="w-12 h-12 rounded-lg flex items-center justify-center" style="background-color: #0c0d10;">
            <svg class="w-8 h-8 os-accent" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M3 5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2h-2.22l.123.489.804.804A1 1 0 0113 18H7a1 1 0 01-.707-1.707l.804-.804L7.22 15H5a2 2 0 01-2-2V5zm5.771 7H5V5h10v7H8.771z" clip-rule="evenodd"/>
            </svg>
        </div>
        <div>
            <div class="text-sm font-medium os-text" x-text="systemInfo.hostname"></div>
            <div class="text-xs os-text-dim" x-text="systemInfo.os"></div>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto p-4 space-y-6">
        {{-- System Information --}}
        <div>
            <div class="text-xs font-medium os-text-muted uppercase tracking-wide mb-3">System Information</div>
            <div class="grid grid-cols-2 gap-3">
                <div class="p-3 rounded" style="background-color: #0c0d10;">
                    <div class="text-xs os-text-muted mb-1">Hostname</div>
                    <div class="text-sm font-mono os-text" x-text="systemInfo.hostname"></div>
                </div>
                <div class="p-3 rounded" style="background-color: #0c0d10;">
                    <div class="text-xs os-text-muted mb-1">Uptime</div>
                    <div class="text-sm font-mono os-text" x-text="systemInfo.uptime"></div>
                </div>
                <div class="p-3 rounded" style="background-color: #0c0d10;">
                    <div class="text-xs os-text-muted mb-1">IP Address</div>
                    <div class="text-sm font-mono os-text" x-text="systemInfo.ip"></div>
                </div>
                <div class="p-3 rounded" style="background-color: #0c0d10;">
                    <div class="text-xs os-text-muted mb-1">MAC Address</div>
                    <div class="text-sm font-mono os-text" x-text="systemInfo.mac"></div>
                </div>
            </div>
        </div>

        {{-- Resource Usage --}}
        <div>
            <div class="text-xs font-medium os-text-muted uppercase tracking-wide mb-3">Resource Usage</div>
            <div class="space-y-3">
                {{-- CPU --}}
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="os-text-dim">CPU</span>
                        <span class="os-text" x-text="resources.cpu + '%'"></span>
                    </div>
                    <div class="h-2 rounded-full overflow-hidden" style="background-color: #2a2d36;">
                        <div class="h-full rounded-full transition-all" style="background-color: #22d3ee;" :style="{ width: resources.cpu + '%' }"></div>
                    </div>
                </div>

                {{-- Memory --}}
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="os-text-dim">Memory</span>
                        <span class="os-text" x-text="resources.memory + '%'"></span>
                    </div>
                    <div class="h-2 rounded-full overflow-hidden" style="background-color: #2a2d36;">
                        <div class="h-full rounded-full transition-all" style="background-color: #4ade80;" :style="{ width: resources.memory + '%' }"></div>
                    </div>
                </div>

                {{-- Disk --}}
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="os-text-dim">Disk</span>
                        <span class="os-text" x-text="resources.disk + '%'"></span>
                    </div>
                    <div class="h-2 rounded-full overflow-hidden" style="background-color: #2a2d36;">
                        <div class="h-full rounded-full transition-all" style="background-color: #fbbf24;" :style="{ width: resources.disk + '%' }"></div>
                    </div>
                </div>

                {{-- Network --}}
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="os-text-dim">Network</span>
                        <span class="os-text" x-text="resources.network + ' KB/s'"></span>
                    </div>
                    <div class="h-2 rounded-full overflow-hidden" style="background-color: #2a2d36;">
                        <div class="h-full rounded-full transition-all" style="background-color: #a78bfa;" :style="{ width: Math.min(resources.network * 2, 100) + '%' }"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Installed Tools --}}
        <div>
            <div class="text-xs font-medium os-text-muted uppercase tracking-wide mb-3">Installed Tools</div>
            <div class="grid grid-cols-3 gap-2">
                <template x-for="tool in tools" :key="tool.name">
                    <div
                        class="p-3 rounded text-center transition-colors"
                        :class="tool.installed ? 'os-bg-hover cursor-pointer' : 'opacity-40 cursor-not-allowed'"
                        :style="{ backgroundColor: '#0c0d10' }"
                    >
                        <div class="w-8 h-8 mx-auto mb-2 rounded flex items-center justify-center" style="background-color: #2a2d36;">
                            <svg class="w-4 h-4" :class="tool.installed ? 'os-accent' : 'os-text-muted'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>
                            </svg>
                        </div>
                        <div class="text-xs truncate" :class="tool.installed ? 'os-text-dim' : 'os-text-muted'" x-text="tool.name"></div>
                        <div class="text-xs mt-1" :class="tool.installed ? 'text-green-400' : 'text-red-400'">
                            <span x-text="tool.installed ? 'Installed' : 'Locked'"></span>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="px-4 py-2 border-t text-xs flex justify-between" style="border-color: #2a2d36; background-color: #0c0d10;">
        <span class="os-text-muted">Kernel: <span class="os-text-dim" x-text="systemInfo.kernel"></span></span>
        <span class="os-text-muted">Status: <span class="text-green-400">Secure</span></span>
    </div>
</div>
