<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>CodeCraft</title>

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full overflow-hidden os-desktop font-sans os-text">

    {{-- Desktop Container --}}
    <div x-data="windowManager()" class="relative h-full flex flex-col">

        {{-- Desktop Area (where windows live) --}}
        <div class="flex-1 relative overflow-hidden" id="desktop-area">

            {{-- Desktop Icons --}}
            <div class="absolute top-4 left-4 flex flex-col gap-4">
                {{-- Terminal Icon --}}
                <button
                    @click="openWindow('terminal', 'Terminal', { width: 750, height: 500 })"
                    class="flex flex-col items-center gap-1 p-2 rounded os-bg-hover transition-colors group w-20"
                >
                    <div class="p-2 rounded os-window border border-opacity-50 group-hover:border-cyan-700 transition-colors" style="border-color: #2a2d36;">
                        <svg class="w-8 h-8 os-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <span class="text-xs os-text-dim group-hover:os-text transition-colors">Terminal</span>
                </button>

                {{-- Files Icon --}}
                <button
                    @click="openWindow('fileExplorer', 'Files', { width: 650, height: 500 })"
                    class="flex flex-col items-center gap-1 p-2 rounded os-bg-hover transition-colors group w-20"
                >
                    <div class="p-2 rounded os-window border border-opacity-50 group-hover:border-cyan-700 transition-colors" style="border-color: #2a2d36;">
                        <svg class="w-8 h-8 os-accent" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/>
                        </svg>
                    </div>
                    <span class="text-xs os-text-dim group-hover:os-text transition-colors">Files</span>
                </button>
            </div>

            {{-- Windows --}}
            <template x-for="window in windows" :key="window.id">
                <div
                    x-show="!window.minimized"
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    class="absolute flex flex-col rounded-lg border overflow-hidden shadow-2xl shadow-black/50"
                    :class="isActive(window.id) ? 'os-window-active os-window' : 'os-window'"
                    :style="`left: ${window.x}px; top: ${window.y}px; width: ${window.width}px; height: ${window.height}px; z-index: ${window.zIndex}; border-color: ${isActive(window.id) ? '#0891b2' : '#2a2d36'};`"
                    @mousedown="focusWindow(window.id)"
                >
                    {{-- Window Header --}}
                    <div
                        class="flex items-center justify-between h-10 px-3 os-window-header border-b window-draggable"
                        style="border-color: #2a2d36;"
                        @mousedown="startDrag($event, window.id)"
                        @dblclick="toggleMaximize(window.id)"
                    >
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium os-text" x-text="window.title"></span>
                        </div>
                        <div class="flex items-center gap-1 window-controls">
                            {{-- Minimize --}}
                            <button
                                @click.stop="minimizeWindow(window.id)"
                                class="w-6 h-6 flex items-center justify-center rounded os-bg-hover transition-colors"
                            >
                                <svg class="w-3 h-3 os-text-dim" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"/>
                                </svg>
                            </button>
                            {{-- Maximize --}}
                            <button
                                @click.stop="toggleMaximize(window.id)"
                                class="w-6 h-6 flex items-center justify-center rounded os-bg-hover transition-colors"
                            >
                                <svg class="w-3 h-3 os-text-dim" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                                </svg>
                            </button>
                            {{-- Close --}}
                            <button
                                @click.stop="closeWindow(window.id)"
                                class="w-6 h-6 flex items-center justify-center rounded hover:bg-red-500/20 transition-colors group"
                            >
                                <svg class="w-3 h-3 os-text-dim group-hover:os-danger" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Window Content --}}
                    <div class="flex-1 overflow-hidden">
                        {{-- Terminal --}}
                        <template x-if="window.type === 'terminal'">
                            @include('desktop.windows.terminal')
                        </template>

                        {{-- File Explorer --}}
                        <template x-if="window.type === 'fileExplorer'">
                            @include('desktop.windows.file-explorer')
                        </template>
                    </div>

                    {{-- Resize Handles --}}
                    <template x-if="!window.maximized">
                        <div>
                            <div class="absolute top-0 left-0 w-2 h-2 cursor-nw-resize" @mousedown.stop="startResize($event, window.id, 'nw')"></div>
                            <div class="absolute top-0 right-0 w-2 h-2 cursor-ne-resize" @mousedown.stop="startResize($event, window.id, 'ne')"></div>
                            <div class="absolute bottom-0 left-0 w-2 h-2 cursor-sw-resize" @mousedown.stop="startResize($event, window.id, 'sw')"></div>
                            <div class="absolute bottom-0 right-0 w-2 h-2 cursor-se-resize" @mousedown.stop="startResize($event, window.id, 'se')"></div>
                            <div class="absolute top-0 left-2 right-2 h-1 cursor-n-resize" @mousedown.stop="startResize($event, window.id, 'n')"></div>
                            <div class="absolute bottom-0 left-2 right-2 h-1 cursor-s-resize" @mousedown.stop="startResize($event, window.id, 's')"></div>
                            <div class="absolute left-0 top-2 bottom-2 w-1 cursor-w-resize" @mousedown.stop="startResize($event, window.id, 'w')"></div>
                            <div class="absolute right-0 top-2 bottom-2 w-1 cursor-e-resize" @mousedown.stop="startResize($event, window.id, 'e')"></div>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        {{-- Taskbar (at bottom like Windows/Linux) --}}
        <div class="h-12 os-taskbar border-t flex items-center px-2 gap-1" style="border-color: #2a2d36;">
            {{-- Start/App Menu Button --}}
            <button class="h-9 w-9 flex items-center justify-center rounded os-bg-hover transition-colors">
                <svg class="w-5 h-5 os-accent" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M3 5a2 2 0 012-2h10a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V5zm11 1H6v8l4-2 4 2V6z" clip-rule="evenodd"/>
                </svg>
            </button>

            <div class="w-px h-6 bg-gray-700 mx-1"></div>

            {{-- Open Windows --}}
            <div class="flex-1 flex items-center gap-1 overflow-x-auto">
                <template x-for="window in taskbarWindows" :key="window.id">
                    <button
                        @click="taskbarClick(window.id)"
                        class="h-9 px-3 flex items-center gap-2 rounded transition-colors min-w-[140px] max-w-[200px] border"
                        :class="isActive(window.id) ? 'os-bg-active os-accent border-cyan-700' : 'os-bg-hover os-text-dim border-transparent'"
                        :style="window.minimized ? 'opacity: 0.5;' : ''"
                    >
                        {{-- Window icon --}}
                        <template x-if="window.type === 'terminal'">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </template>
                        <template x-if="window.type === 'fileExplorer'">
                            <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/>
                            </svg>
                        </template>
                        <span class="text-sm truncate" x-text="window.title"></span>
                    </button>
                </template>
            </div>

            {{-- System Tray (right side) --}}
            <div class="flex items-center gap-3 px-3 border-l border-gray-700">
                {{-- Network indicator --}}
                <div class="flex items-center gap-1">
                    <div class="w-2 h-2 rounded-full bg-green-500"></div>
                    <span class="text-xs os-text-muted">Online</span>
                </div>

                {{-- Clock --}}
                <span
                    class="text-sm os-text-dim font-mono"
                    x-data="{ time: '' }"
                    x-init="setInterval(() => time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }), 1000)"
                    x-text="time"
                ></span>
            </div>
        </div>
    </div>

</body>
</html>
