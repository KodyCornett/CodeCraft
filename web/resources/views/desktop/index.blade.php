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
        <div class="flex-1 relative overflow-hidden" id="desktop-area" @click="startMenuOpen = false">

            {{-- Desktop Icons --}}
            <div class="absolute top-4 left-4 flex flex-col gap-4">
                {{-- Terminal Icon --}}
                <button
                    @click.stop="openWindow('terminal', 'Terminal', { width: 750, height: 500 })"
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
                    @click.stop="openWindow('fileExplorer', 'Documents', { width: 650, height: 500 })"
                    class="flex flex-col items-center gap-1 p-2 rounded os-bg-hover transition-colors group w-20"
                >
                    <div class="p-2 rounded os-window border border-opacity-50 group-hover:border-cyan-700 transition-colors" style="border-color: #2a2d36;">
                        <svg class="w-8 h-8 os-accent" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/>
                        </svg>
                    </div>
                    <span class="text-xs os-text-dim group-hover:os-text transition-colors">Documents</span>
                </button>

                {{-- Node Manager Icon --}}
                <button
                    @click.stop="openWindow('nodeManager', 'Node Manager', { width: 900, height: 600 })"
                    class="flex flex-col items-center gap-1 p-2 rounded os-bg-hover transition-colors group w-20"
                >
                    <div class="p-2 rounded os-window border border-opacity-50 group-hover:border-cyan-700 transition-colors" style="border-color: #2a2d36;">
                        <svg class="w-8 h-8 os-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>
                        </svg>
                    </div>
                    <span class="text-xs os-text-dim group-hover:os-text transition-colors text-center leading-tight">Node<br>Manager</span>
                </button>

                {{-- Browser Icon --}}
                <button
                    @click.stop="openWindow('browser', 'Matrix Browser', { width: 900, height: 600 })"
                    class="flex flex-col items-center gap-1 p-2 rounded os-bg-hover transition-colors group w-20"
                >
                    <div class="p-2 rounded os-window border border-opacity-50 group-hover:border-cyan-700 transition-colors" style="border-color: #2a2d36;">
                        <svg class="w-8 h-8 os-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                        </svg>
                    </div>
                    <span class="text-xs os-text-dim group-hover:os-text transition-colors text-center leading-tight">Matrix<br>Browser</span>
                </button>

                {{-- Messages Icon --}}
                <button
                    @click.stop="openWindow('messages', 'Messages', { width: 700, height: 550 })"
                    class="flex flex-col items-center gap-1 p-2 rounded os-bg-hover transition-colors group w-20"
                >
                    <div class="p-2 rounded os-window border border-opacity-50 group-hover:border-cyan-700 transition-colors" style="border-color: #2a2d36;">
                        <svg class="w-8 h-8 os-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <span class="text-xs os-text-dim group-hover:os-text transition-colors">Messages</span>
                </button>

                {{-- Sentinel Icon --}}
                <button
                    @click.stop="openWindow('sentinel', 'Sentinel', { width: 750, height: 500 })"
                    class="flex flex-col items-center gap-1 p-2 rounded os-bg-hover transition-colors group w-20"
                >
                    <div class="p-2 rounded os-window border border-opacity-50 group-hover:border-cyan-700 transition-colors" style="border-color: #2a2d36;">
                        <svg class="w-8 h-8 os-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <span class="text-xs os-text-dim group-hover:os-text transition-colors">Sentinel</span>
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
                    @click.stop
                >
                    {{-- Window Header --}}
                    <div
                        class="flex items-center justify-between h-10 px-3 os-window-header border-b window-draggable"
                        style="border-color: #2a2d36;"
                        @mousedown="startDrag($event, window.id)"
                        @dblclick="toggleMaximize(window.id)"
                    >
                        <div class="flex items-center gap-2">
                            {{-- Window Icon --}}
                            <template x-if="window.type === 'terminal'">
                                <svg class="w-4 h-4 os-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </template>
                            <template x-if="window.type === 'fileExplorer'">
                                <svg class="w-4 h-4 os-accent" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/>
                                </svg>
                            </template>
                            <template x-if="window.type === 'nodeManager'">
                                <svg class="w-4 h-4 os-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>
                                </svg>
                            </template>
                            <template x-if="window.type === 'contacts'">
                                <svg class="w-4 h-4 os-accent" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                                </svg>
                            </template>
                            <template x-if="window.type === 'myPC'">
                                <svg class="w-4 h-4 os-accent" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3 5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2h-2.22l.123.489.804.804A1 1 0 0113 18H7a1 1 0 01-.707-1.707l.804-.804L7.22 15H5a2 2 0 01-2-2V5zm5.771 7H5V5h10v7H8.771z" clip-rule="evenodd"/>
                                </svg>
                            </template>
                            <template x-if="window.type === 'browser'">
                                <svg class="w-4 h-4 os-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                                </svg>
                            </template>
                            <template x-if="window.type === 'messages'">
                                <svg class="w-4 h-4 os-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                            </template>
                            <template x-if="window.type === 'sentinel'">
                                <svg class="w-4 h-4 os-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            </template>
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

                        {{-- File Explorer / Documents --}}
                        <template x-if="window.type === 'fileExplorer'">
                            @include('desktop.windows.file-explorer')
                        </template>

                        {{-- Node Manager --}}
                        <template x-if="window.type === 'nodeManager'">
                            @include('desktop.windows.node-manager')
                        </template>

                        {{-- Contacts --}}
                        <template x-if="window.type === 'contacts'">
                            @include('desktop.windows.contacts')
                        </template>

                        {{-- My PC --}}
                        <template x-if="window.type === 'myPC'">
                            @include('desktop.windows.my-pc')
                        </template>

                        {{-- Browser --}}
                        <template x-if="window.type === 'browser'">
                            @include('desktop.windows.browser')
                        </template>

                        {{-- Messages --}}
                        <template x-if="window.type === 'messages'">
                            @include('desktop.windows.messages')
                        </template>

                        {{-- Sentinel --}}
                        <template x-if="window.type === 'sentinel'">
                            @include('desktop.windows.sentinel')
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

        {{-- Start Menu (popup) --}}
        <div
            x-show="startMenuOpen"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-2"
            @click.stop
            class="absolute bottom-14 left-2 w-64 rounded-lg border shadow-2xl shadow-black/50 overflow-hidden"
            style="background-color: #13151a; border-color: #2a2d36; z-index: 9999;"
        >
            {{-- Menu Header --}}
            <div class="px-4 py-3 border-b" style="border-color: #2a2d36; background-color: #1a1c23;">
                <div class="text-sm font-medium os-text">Programs</div>
            </div>

            {{-- Menu Items --}}
            <div class="py-2">
                {{-- Terminal --}}
                <button
                    @click="openWindow('terminal', 'Terminal', { width: 750, height: 500 }); startMenuOpen = false"
                    class="w-full px-4 py-2 flex items-center gap-3 os-bg-hover transition-colors"
                >
                    <svg class="w-5 h-5 os-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span class="text-sm os-text">Terminal</span>
                </button>

                {{-- Node Manager --}}
                <button
                    @click="openWindow('nodeManager', 'Node Manager', { width: 900, height: 600 }); startMenuOpen = false"
                    class="w-full px-4 py-2 flex items-center gap-3 os-bg-hover transition-colors"
                >
                    <svg class="w-5 h-5 os-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>
                    </svg>
                    <span class="text-sm os-text">Node Manager</span>
                </button>

                {{-- Browser --}}
                <button
                    @click="openWindow('browser', 'Matrix Browser', { width: 900, height: 600 }); startMenuOpen = false"
                    class="w-full px-4 py-2 flex items-center gap-3 os-bg-hover transition-colors"
                >
                    <svg class="w-5 h-5 os-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                    </svg>
                    <span class="text-sm os-text">Matrix Browser</span>
                </button>

                {{-- Messages --}}
                <button
                    @click="openWindow('messages', 'Messages', { width: 700, height: 550 }); startMenuOpen = false"
                    class="w-full px-4 py-2 flex items-center gap-3 os-bg-hover transition-colors"
                >
                    <svg class="w-5 h-5 os-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <span class="text-sm os-text">Messages</span>
                </button>

                {{-- Sentinel --}}
                <button
                    @click="openWindow('sentinel', 'Sentinel', { width: 750, height: 500 }); startMenuOpen = false"
                    class="w-full px-4 py-2 flex items-center gap-3 os-bg-hover transition-colors"
                >
                    <svg class="w-5 h-5 os-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    <span class="text-sm os-text">Sentinel</span>
                </button>

                <div class="my-2 border-t" style="border-color: #2a2d36;"></div>

                {{-- Documents --}}
                <button
                    @click="openWindow('fileExplorer', 'Documents', { width: 650, height: 500 }); startMenuOpen = false"
                    class="w-full px-4 py-2 flex items-center gap-3 os-bg-hover transition-colors"
                >
                    <svg class="w-5 h-5 os-accent" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/>
                    </svg>
                    <span class="text-sm os-text">Documents</span>
                </button>

                {{-- Contacts --}}
                <button
                    @click="openWindow('contacts', 'Contacts', { width: 500, height: 450 }); startMenuOpen = false"
                    class="w-full px-4 py-2 flex items-center gap-3 os-bg-hover transition-colors"
                >
                    <svg class="w-5 h-5 os-accent" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                    </svg>
                    <span class="text-sm os-text">Contacts</span>
                </button>

                {{-- My PC --}}
                <button
                    @click="openWindow('myPC', 'My PC', { width: 600, height: 450 }); startMenuOpen = false"
                    class="w-full px-4 py-2 flex items-center gap-3 os-bg-hover transition-colors"
                >
                    <svg class="w-5 h-5 os-accent" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M3 5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2h-2.22l.123.489.804.804A1 1 0 0113 18H7a1 1 0 01-.707-1.707l.804-.804L7.22 15H5a2 2 0 01-2-2V5zm5.771 7H5V5h10v7H8.771z" clip-rule="evenodd"/>
                    </svg>
                    <span class="text-sm os-text">My PC</span>
                </button>
            </div>

            {{-- Menu Footer --}}
            <div class="px-4 py-2 border-t flex items-center justify-between" style="border-color: #2a2d36; background-color: #0c0d10;">
                <span class="text-xs os-text-muted">CodeCraft OS v0.1</span>
            </div>
        </div>

        {{-- Taskbar (at bottom like Windows/Linux) --}}
        <div class="h-12 os-taskbar border-t flex items-center px-2 gap-1" style="border-color: #2a2d36;">
            {{-- Start/App Menu Button --}}
            <button
                @click.stop="startMenuOpen = !startMenuOpen"
                class="h-9 w-9 flex items-center justify-center rounded transition-colors"
                :class="startMenuOpen ? 'os-bg-active' : 'os-bg-hover'"
            >
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
                        <template x-if="window.type === 'nodeManager'">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/>
                            </svg>
                        </template>
                        <template x-if="window.type === 'contacts'">
                            <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                            </svg>
                        </template>
                        <template x-if="window.type === 'myPC'">
                            <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3 5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2h-2.22l.123.489.804.804A1 1 0 0113 18H7a1 1 0 01-.707-1.707l.804-.804L7.22 15H5a2 2 0 01-2-2V5zm5.771 7H5V5h10v7H8.771z" clip-rule="evenodd"/>
                            </svg>
                        </template>
                        <template x-if="window.type === 'browser'">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                            </svg>
                        </template>
                        <template x-if="window.type === 'messages'">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </template>
                        <template x-if="window.type === 'sentinel'">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
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
