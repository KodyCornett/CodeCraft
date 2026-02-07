<div
    x-data="terminal()"
    class="h-full flex flex-col os-terminal font-mono text-sm"
    @click="focusInput()"
>
    {{-- Output Area --}}
    <div
        x-ref="outputContainer"
        class="flex-1 overflow-y-auto p-4 space-y-1"
    >
        <template x-for="line in history" :key="line.id">
            <div
                class="whitespace-pre-wrap break-all leading-relaxed"
                :class="{
                    'os-text': line.type === 'command',
                    'os-text-dim': line.type === 'output',
                    'os-danger': line.type === 'error',
                    'os-accent-dim': line.type === 'system'
                }"
                x-text="line.text"
            ></div>
        </template>

        {{-- Processing indicator --}}
        <div x-show="isProcessing" class="flex items-center gap-2 os-accent-dim">
            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>Processing...</span>
        </div>
    </div>

    {{-- Input Line --}}
    <div class="flex items-center px-4 py-3 border-t" style="border-color: #1a1c23; background-color: #0a0b0d;">
        <span class="os-accent mr-2 select-none font-medium" x-text="prompt"></span>
        <input
            x-ref="input"
            type="text"
            x-model="currentInput"
            @keydown.enter="executeCommand()"
            @keydown="handleKeydown($event)"
            :disabled="isProcessing"
            class="flex-1 bg-transparent outline-none os-text placeholder-gray-600"
            style="caret-color: #22d3ee;"
            placeholder=""
            autocomplete="off"
            autocorrect="off"
            autocapitalize="off"
            spellcheck="false"
        >
        <span class="w-2 h-5 cursor-blink ml-0.5" style="background-color: #22d3ee;" x-show="!isProcessing"></span>
    </div>
</div>
