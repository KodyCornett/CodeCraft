<div x-data="fileExplorer()" class="h-full flex flex-col os-window">
    {{-- Toolbar --}}
    <div class="flex items-center gap-2 px-3 py-2 border-b" style="border-color: #2a2d36; background-color: #1a1c23;">
        {{-- Back button --}}
        <button
            @click="navigateUp()"
            :disabled="currentPath === '/'"
            class="p-1.5 rounded os-bg-hover disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
        >
            <svg class="w-4 h-4 os-text-dim" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </button>

        {{-- Breadcrumbs --}}
        <div class="flex-1 flex items-center gap-1 text-sm overflow-x-auto">
            <template x-for="(crumb, index) in breadcrumbs" :key="crumb.path">
                <div class="flex items-center">
                    <button
                        @click="navigateToBreadcrumb(crumb.path)"
                        class="px-1.5 py-0.5 rounded os-bg-hover os-text-dim hover:os-text transition-colors"
                        x-text="crumb.name"
                    ></button>
                    <span x-show="index < breadcrumbs.length - 1" class="os-text-muted mx-1">/</span>
                </div>
            </template>
        </div>
    </div>

    {{-- Content Area --}}
    <div class="flex-1 overflow-hidden flex">
        {{-- File list (or file viewer) --}}
        <div class="flex-1 overflow-y-auto p-3">
            {{-- Loading state --}}
            <div x-show="isLoading" class="flex items-center justify-center h-full">
                <svg class="w-8 h-8 os-accent-dim animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </div>

            {{-- File Viewer --}}
            <div x-show="viewingFile" x-cloak class="h-full flex flex-col">
                <div class="flex items-center justify-between pb-2 mb-2 border-b" style="border-color: #2a2d36;">
                    <span class="text-sm font-medium os-text" x-text="viewingFile?.name"></span>
                    <button
                        @click="closeFileView()"
                        class="p-1 rounded os-bg-hover transition-colors"
                    >
                        <svg class="w-4 h-4 os-text-dim" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>
                <pre class="flex-1 overflow-auto text-sm font-mono os-text-dim whitespace-pre-wrap p-3 rounded" style="background-color: #0c0d10;" x-text="fileContent"></pre>
            </div>

            {{-- File Grid --}}
            <div x-show="!viewingFile && !isLoading" class="grid grid-cols-4 gap-2">
                <template x-for="file in files" :key="file.name">
                    <button
                        @click="selectFile(file)"
                        @dblclick="handleDoubleClick(file)"
                        class="flex flex-col items-center gap-1 p-3 rounded-lg transition-colors"
                        :class="selectedFile?.name === file.name ? 'os-bg-active' : 'os-bg-hover'"
                    >
                        <div x-html="getIcon(file)"></div>
                        <span
                            class="text-xs text-center break-all line-clamp-2"
                            :class="selectedFile?.name === file.name ? 'os-accent' : 'os-text-dim'"
                            x-text="file.name"
                        ></span>
                    </button>
                </template>

                {{-- Empty state --}}
                <div x-show="files.length === 0" class="col-span-4 flex flex-col items-center justify-center py-12 os-text-muted">
                    <svg class="w-12 h-12 mb-2" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/>
                    </svg>
                    <span class="text-sm">This folder is empty</span>
                </div>
            </div>
        </div>
    </div>
</div>
