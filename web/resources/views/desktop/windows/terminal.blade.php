<div
    x-data="terminal()"
    class="h-full flex flex-col bg-[--color-terminal-bg] font-mono text-sm"
    @click="focusInput()"
>
    {{-- Output Area --}}
    <div
        x-ref="outputContainer"
        class="flex-1 overflow-y-auto p-3 space-y-0.5"
    >
        <template x-for="line in history" :key="line.id">
            <div
                class="whitespace-pre-wrap break-all leading-relaxed"
                :class="getLineClass(line.type)"
                x-text="line.text"
            ></div>
        </template>

        {{-- Processing indicator --}}
        <div x-show="isProcessing" class="flex items-center gap-2 text-[--color-accent-dim]">
            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>Processing...</span>
        </div>
    </div>

    {{-- Input Line --}}
    <div class="flex items-center px-3 py-2 border-t border-[--color-window-border]">
        <span class="text-[--color-accent] mr-2 select-none" x-text="prompt"></span>
        <input
            x-ref="input"
            type="text"
            x-model="currentInput"
            @keydown.enter="executeCommand()"
            @keydown="handleKeydown($event)"
            :disabled="isProcessing"
            class="flex-1 bg-transparent outline-none text-[--color-text-primary] placeholder-[--color-text-muted] caret-[--color-accent]"
            placeholder=""
            autocomplete="off"
            autocorrect="off"
            autocapitalize="off"
            spellcheck="false"
        >
        <span class="w-2 h-4 bg-[--color-accent] cursor-blink" x-show="!isProcessing"></span>
    </div>
</div>
