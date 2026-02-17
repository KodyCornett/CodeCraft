<div x-data="secureChannel()" class="h-full flex flex-col os-window" style="background-color: #0a0c10;">
    {{-- Header --}}
    <div class="flex items-center justify-between px-4 py-2 border-b" style="border-color: #2a2d36; background-color: #13151a;">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 os-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
            <span class="text-sm font-medium os-text">Secure Channel</span>
        </div>
        <div class="flex items-center gap-2">
            <span
                class="w-2 h-2 rounded-full"
                :class="{
                    'bg-yellow-400 animate-pulse': connectionPhase === 'connecting',
                    'bg-green-400': connectionPhase === 'connected',
                    'bg-red-400': connectionError
                }"
            ></span>
            <span class="text-xs os-text-dim" x-text="connectionPhase === 'connected' ? 'ENCRYPTED' : connectionPhase.toUpperCase()"></span>
        </div>
    </div>

    {{-- Content Area --}}
    <div class="flex-1 overflow-y-auto p-4">
        {{-- Connecting Phase - VPN Simulation --}}
        <div x-show="connectionPhase === 'connecting'" class="space-y-3">
            <div class="text-sm os-accent font-mono mb-4">Establishing secure connection...</div>

            <template x-for="(step, index) in connectionSteps" :key="index">
                <div class="flex items-center gap-3 text-sm font-mono">
                    {{-- Status icon --}}
                    <span :class="step.status === 'done' || step.status === 'complete' ? 'text-green-400' : 'text-yellow-400 animate-pulse'">
                        <template x-if="step.status === 'routing'">
                            <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                        </template>
                        <template x-if="step.status === 'done' || step.status === 'complete'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </template>
                    </span>

                    {{-- Route info --}}
                    <template x-if="step.ip">
                        <div class="flex items-center gap-2">
                            <span class="os-accent" x-text="step.ip"></span>
                            <span class="os-text-dim">&rarr;</span>
                            <span class="os-text" x-text="step.location"></span>
                        </div>
                    </template>

                    {{-- Final message --}}
                    <template x-if="step.message">
                        <span class="text-green-400" x-text="step.message"></span>
                    </template>
                </div>
            </template>
        </div>

        {{-- Connection Error --}}
        <div x-show="connectionError" class="text-center py-8">
            <div class="text-4xl mb-4">&#x26A0;</div>
            <p class="text-red-400" x-text="connectionError"></p>
            <button @click="simulateVpnConnection()" class="mt-4 px-4 py-2 bg-cyan-600 hover:bg-cyan-500 rounded text-sm transition-colors">
                Retry Connection
            </button>
        </div>

        {{-- Connected - Job Offer --}}
        <div x-show="connectionPhase === 'connected' && !puzzleActive && !puzzleSolved" class="space-y-4">
            {{-- Loading job --}}
            <div x-show="isLoadingJob" class="text-center py-8">
                <div class="text-sm os-text-dim animate-pulse">Receiving transmission...</div>
            </div>

            {{-- Job Details --}}
            <template x-if="jobData && !isLoadingJob">
                <div class="space-y-4">
                    {{-- From header --}}
                    <div class="flex items-center gap-3 pb-3 border-b" style="border-color: #2a2d36;">
                        <span class="text-2xl" x-text="jobData.senderAvatar || '&#x1F47B;'"></span>
                        <div>
                            <div class="font-medium os-text" x-text="jobData.senderName || 'Anonymous'"></div>
                            <div class="text-xs os-text-muted">Encrypted Transmission</div>
                        </div>
                    </div>

                    {{-- Job description --}}
                    <div class="os-text text-sm leading-relaxed whitespace-pre-line" x-text="jobData.description"></div>

                    {{-- Job details card --}}
                    <div class="p-4 rounded-lg border" style="background-color: #13151a; border-color: #2a2d36;">
                        <h3 class="text-sm font-medium os-accent mb-3">Job Details</h3>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <span class="os-text-muted">Target:</span>
                                <span class="os-text font-mono ml-2" x-text="jobData.target"></span>
                            </div>
                            <div>
                                <span class="os-text-muted">Payout:</span>
                                <span class="text-green-400 ml-2">&sect;<span x-text="jobData.payout?.toLocaleString()"></span></span>
                            </div>
                            <div>
                                <span class="os-text-muted">Objective:</span>
                                <span class="os-text ml-2" x-text="jobData.objective"></span>
                            </div>
                            <div>
                                <span class="os-text-muted">Difficulty:</span>
                                <span
                                    class="ml-2 px-2 py-0.5 rounded text-xs"
                                    :class="getDifficultyBgColor(jobData.difficulty) + ' ' + getDifficultyColor(jobData.difficulty)"
                                    x-text="jobData.difficulty"
                                ></span>
                            </div>
                        </div>
                    </div>

                    {{-- Action buttons --}}
                    <div class="flex gap-3 pt-2">
                        <button
                            @click="showNegotiationWarning()"
                            class="flex-1 px-4 py-2 rounded text-sm border transition-colors"
                            :class="showNegotiateWarning ? 'border-yellow-500 bg-yellow-500/10' : 'border-gray-600 os-bg-hover'"
                        >
                            Negotiate
                        </button>
                        <button
                            @click="acceptJob()"
                            class="flex-1 px-4 py-2 bg-cyan-600 hover:bg-cyan-500 rounded text-sm transition-colors font-medium"
                        >
                            Accept Job
                        </button>
                    </div>

                    {{-- Negotiation Warning --}}
                    <div
                        x-show="showNegotiateWarning"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 -translate-y-2"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        class="p-4 bg-yellow-500/10 border border-yellow-500/30 rounded"
                    >
                        <p class="text-yellow-400 text-sm font-medium mb-1">&#x26A0; Negotiation is reputation-based</p>
                        <p class="text-yellow-400/70 text-xs mb-3">Your current standing with this contact will affect the outcome. A failed negotiation may result in reduced payout or job withdrawal.</p>

                        <div class="flex gap-2">
                            <button
                                @click="attemptNegotiate()"
                                :disabled="isNegotiating"
                                class="px-3 py-1.5 text-sm bg-yellow-600 hover:bg-yellow-500 rounded transition-colors disabled:opacity-50"
                            >
                                <span x-show="!isNegotiating">Proceed</span>
                                <span x-show="isNegotiating">Negotiating...</span>
                            </button>
                            <button
                                @click="showNegotiateWarning = false"
                                class="px-3 py-1.5 text-sm os-text-dim os-bg-hover rounded transition-colors"
                            >
                                Cancel
                            </button>
                        </div>

                        {{-- Negotiation result --}}
                        <div x-show="negotiationResult" class="mt-3 pt-3 border-t border-yellow-500/30">
                            <template x-if="negotiationResult?.success">
                                <p class="text-green-400 text-sm">&#x2713; <span x-text="negotiationResult.message"></span></p>
                            </template>
                            <template x-if="negotiationResult && !negotiationResult.success">
                                <p class="text-red-400 text-sm">&#x2717; <span x-text="negotiationResult.message"></span></p>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- Puzzle Phase --}}
        <div x-show="puzzleActive && !puzzleSolved" class="space-y-4">
            <div class="text-center">
                <div class="text-sm os-accent mb-2">ACCESS VERIFICATION REQUIRED</div>
                <p class="text-xs os-text-dim mb-4" x-text="puzzleHint"></p>
            </div>

            {{-- Puzzle display --}}
            <div class="font-mono os-accent text-3xl p-6 bg-black/50 rounded-lg text-center border border-cyan-800/50" x-text="puzzleContent"></div>

            {{-- Answer input --}}
            <div class="space-y-2">
                <input
                    x-model="puzzleAnswer"
                    @keydown.enter="submitPuzzle()"
                    type="text"
                    class="w-full p-3 bg-black/50 border border-cyan-800 rounded os-text text-center uppercase font-mono text-lg tracking-widest"
                    placeholder="ENTER DECODED WORD"
                    autofocus
                >

                {{-- Error message --}}
                <div x-show="puzzleError" class="text-red-400 text-sm text-center" x-text="puzzleError"></div>
            </div>

            {{-- Puzzle actions --}}
            <div class="flex gap-3">
                <button
                    @click="cancelPuzzle()"
                    class="flex-1 px-4 py-2 os-text-dim os-bg-hover rounded text-sm transition-colors"
                >
                    Cancel
                </button>
                <button
                    @click="submitPuzzle()"
                    :disabled="!puzzleAnswer.trim()"
                    class="flex-1 px-4 py-2 bg-cyan-600 hover:bg-cyan-500 rounded text-sm transition-colors font-medium disabled:opacity-50"
                >
                    Verify
                </button>
            </div>
        </div>

        {{-- Puzzle Solved / Job Accepted --}}
        <div x-show="puzzleSolved" class="text-center py-8 space-y-4">
            <div class="text-6xl">&#x1F512;</div>
            <div class="text-green-400 font-medium">ACCESS GRANTED</div>
            <p class="text-sm os-text-dim">Mission briefing encrypted and sent to your Messages.</p>
            <p class="text-xs os-accent">Check your inbox for the passcode-protected details.</p>
        </div>
    </div>

    {{-- Footer --}}
    <div class="px-4 py-2 border-t flex items-center justify-between" style="border-color: #2a2d36; background-color: #13151a;">
        <div class="flex items-center gap-2">
            <svg class="w-3 h-3 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
            </svg>
            <span class="text-xs os-text-muted">256-bit encrypted</span>
        </div>
        <span class="text-xs os-text-muted font-mono">Channel ID: <span x-text="jobId || '???'"></span></span>
    </div>
</div>
