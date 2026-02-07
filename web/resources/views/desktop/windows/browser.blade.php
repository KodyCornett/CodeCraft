<div x-data="browser()" class="h-full flex flex-col os-window">
    {{-- Browser Toolbar --}}
    <div class="flex items-center gap-2 px-2 py-2 border-b" style="border-color: #2a2d36; background-color: #1a1c23;">
        {{-- Navigation Buttons --}}
        <div class="flex items-center gap-1">
            <button
                @click="goBack()"
                :disabled="!canGoBack"
                class="p-1.5 rounded transition-colors"
                :class="canGoBack ? 'os-bg-hover os-text-dim' : 'os-text-muted opacity-50 cursor-not-allowed'"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
            <button
                @click="goForward()"
                :disabled="!canGoForward"
                class="p-1.5 rounded transition-colors"
                :class="canGoForward ? 'os-bg-hover os-text-dim' : 'os-text-muted opacity-50 cursor-not-allowed'"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
            <button
                @click="refresh()"
                :disabled="!currentUrl || isLoading"
                class="p-1.5 rounded transition-colors"
                :class="currentUrl && !isLoading ? 'os-bg-hover os-text-dim' : 'os-text-muted opacity-50 cursor-not-allowed'"
            >
                <svg class="w-4 h-4" :class="isLoading ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
            </button>
            <button
                @click="goHome()"
                class="p-1.5 rounded os-bg-hover os-text-dim transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
            </button>
        </div>

        {{-- URL Bar --}}
        <div class="flex-1 flex items-center rounded px-3 py-1.5" style="background-color: #0c0d10;">
            <span class="os-text-muted text-sm mr-2">matrix://</span>
            <input
                x-ref="urlInput"
                type="text"
                :value="currentUrl"
                @keydown.enter="handleUrlSubmit()"
                class="flex-1 bg-transparent outline-none text-sm os-text font-mono"
                placeholder="Enter address..."
            >
        </div>
    </div>

    {{-- Content Area --}}
    <div class="flex-1 flex overflow-hidden">
        {{-- Sidebar Navigation (when on a site) --}}
        <div
            x-show="navigation.length > 0 && !showBookmarks"
            class="w-44 border-r overflow-y-auto flex-shrink-0"
            style="border-color: #2a2d36; background-color: #13151a;"
        >
            <div class="p-3">
                <div class="flex items-center gap-2 mb-3">
                    <span x-text="siteIcon"></span>
                    <span class="text-sm font-medium os-text truncate" x-text="siteName"></span>
                </div>
                <nav class="space-y-1">
                    <template x-for="item in navigation" :key="item.path">
                        <button
                            @click="navigateTo(item.path)"
                            class="w-full px-2 py-1.5 rounded text-left text-sm transition-colors flex items-center gap-2"
                            :class="currentPath === item.path ? 'os-bg-active os-accent' : 'os-text-dim os-bg-hover'"
                        >
                            <span x-text="item.icon || '•'"></span>
                            <span x-text="item.label"></span>
                        </button>
                    </template>
                </nav>
            </div>
        </div>

        {{-- Main Content --}}
        <div class="flex-1 overflow-y-auto">
            {{-- Loading State --}}
            <div x-show="isLoading" class="flex items-center justify-center h-full">
                <div class="text-center">
                    <svg class="w-8 h-8 os-accent animate-spin mx-auto mb-3" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <div class="text-sm os-text-dim">Loading...</div>
                </div>
            </div>

            {{-- Error State --}}
            <div x-show="error && !isLoading" class="flex items-center justify-center h-full">
                <div class="text-center p-8">
                    <div class="text-4xl mb-4">🔌</div>
                    <h2 class="text-lg font-medium os-text mb-2">Connection Error</h2>
                    <p class="text-sm os-text-dim" x-text="error"></p>
                    <button @click="goHome()" class="mt-4 px-4 py-2 rounded text-sm os-accent border border-cyan-700 os-bg-hover transition-colors">
                        Return Home
                    </button>
                </div>
            </div>

            {{-- Bookmarks/Home Page --}}
            <div x-show="showBookmarks && !isLoading" class="p-6">
                <div class="max-w-2xl mx-auto">
                    <div class="text-center mb-8">
                        <h1 class="text-2xl font-medium os-text mb-2">Matrix Browser</h1>
                        <p class="text-sm os-text-dim">Access the underground network</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <template x-for="bookmark in bookmarks" :key="bookmark.domain">
                            <button
                                @click="navigate(bookmark.domain)"
                                class="p-4 rounded-lg text-left transition-colors border"
                                style="background-color: #13151a; border-color: #2a2d36;"
                                onmouseover="this.style.borderColor='#0891b2'"
                                onmouseout="this.style.borderColor='#2a2d36'"
                            >
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="text-2xl" x-text="bookmark.icon"></span>
                                    <div>
                                        <div class="font-medium os-text" x-text="bookmark.name"></div>
                                        <div class="text-xs font-mono os-text-muted" x-text="bookmark.domain"></div>
                                    </div>
                                </div>
                                <p class="text-sm os-text-dim" x-text="bookmark.description"></p>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Page Content --}}
            <div
                x-show="pageContent && !isLoading && !error && !showBookmarks"
                class="browser-content p-4"
                @click="handleContentClick($event)"
                x-html="pageContent"
            ></div>
        </div>
    </div>
</div>
