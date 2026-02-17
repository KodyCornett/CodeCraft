<div x-data="jobsBoard()" x-init="init()" @destroy="destroy()" class="h-full flex flex-col os-window">

    {{-- Header --}}
    <div class="flex items-center justify-between px-3 py-2 border-b" style="border-color: #2a2d36; background-color: #1a1c23;">
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 os-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2-2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                <span class="text-sm font-medium os-text">JOBS BOARD</span>
            </div>
            <template x-if="hasActiveMission()">
                <div class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></span>
                    <span class="text-xs text-cyan-400 font-mono">ACTIVE</span>
                </div>
            </template>
        </div>
        <div class="text-xs os-text-muted font-mono" x-text="hasActiveMission() ? elapsedTime(activeMission.elapsedSeconds) : '--:--'"></div>
    </div>

    {{-- Tab Bar --}}
    <div class="flex border-b" style="border-color: #2a2d36; background-color: #13151a;">
        <button
            @click="tab = 'active'"
            class="px-4 py-2 text-xs font-medium font-mono transition-colors border-b-2"
            :class="tab === 'active' ? 'text-cyan-400 border-cyan-500' : 'os-text-muted border-transparent hover:os-text'"
        >ACTIVE</button>
        <button
            @click="tab = 'board'"
            class="px-4 py-2 text-xs font-medium font-mono transition-colors border-b-2"
            :class="tab === 'board' ? 'text-cyan-400 border-cyan-500' : 'os-text-muted border-transparent hover:os-text'"
        >BOARD</button>
        <button
            @click="tab = 'history'"
            class="px-4 py-2 text-xs font-medium font-mono transition-colors border-b-2"
            :class="tab === 'history' ? 'text-cyan-400 border-cyan-500' : 'os-text-muted border-transparent hover:os-text'"
        >HISTORY</button>
    </div>

    {{-- Content --}}
    <div class="flex-1 overflow-y-auto" style="background-color: #0c0d10;">

        {{-- ACTIVE TAB --}}
        <template x-if="tab === 'active'">
            <div class="p-4">
                {{-- No Active Mission --}}
                <template x-if="!hasActiveMission()">
                    <div class="flex flex-col items-center justify-center h-48 gap-3">
                        <svg class="w-10 h-10 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2-2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        <div class="text-sm os-text-muted font-mono">No active job.</div>
                        <div class="text-xs os-text-muted">Check the BOARD tab for available work.</div>
                    </div>
                </template>

                {{-- Active Mission --}}
                <template x-if="hasActiveMission()">
                    <div class="flex flex-col gap-4">

                        {{-- Mission Header Card --}}
                        <div
                            class="rounded border-l-2 p-3"
                            :class="missionHeaderClass()"
                            style="background-color: #13151a; border-top-color: #2a2d36; border-right-color: #2a2d36; border-bottom-color: #2a2d36;"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex-1">
                                    <div class="text-sm font-medium os-text font-mono" x-text="activeMission.title"></div>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-xs font-mono" :class="factionColor(activeMission.contact)" x-text="'[' + (activeMission.contact || 'unknown') + ']'"></span>
                                        <span class="text-xs os-text-muted">·</span>
                                        <span class="text-xs font-mono text-yellow-400" x-text="'§' + (activeMission.reward || 0)"></span>
                                    </div>
                                </div>
                                <div class="text-right shrink-0">
                                    <div class="text-xs os-text-muted font-mono">elapsed</div>
                                    <div class="text-sm font-mono os-text" x-text="elapsedTime(activeMission.elapsedSeconds)"></div>
                                </div>
                            </div>
                        </div>

                        {{-- Failure Banner --}}
                        <template x-if="activeMission.failed">
                            <div class="flex items-center gap-2 px-3 py-2 rounded border border-red-500/40 bg-red-500/10">
                                <svg class="w-4 h-4 text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <div>
                                    <div class="text-xs font-mono font-medium text-red-400">JOB FAILED</div>
                                    <div class="text-xs text-red-300/70" x-text="activeMission.failureReason || 'Mission objectives not met.'"></div>
                                </div>
                            </div>
                        </template>

                        {{-- Complete Banner --}}
                        <template x-if="activeMission.isComplete && !activeMission.failed">
                            <div class="flex items-center gap-2 px-3 py-2 rounded border border-green-500/40 bg-green-500/10">
                                <svg class="w-4 h-4 text-green-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <div>
                                    <div class="text-xs font-mono font-medium text-green-400">READY TO COLLECT</div>
                                    <div class="text-xs text-green-300/70">All objectives complete. Awaiting confirmation.</div>
                                </div>
                            </div>
                        </template>

                        {{-- Progress --}}
                        <div class="flex items-center justify-between text-xs os-text-muted font-mono">
                            <span>OBJECTIVES</span>
                            <span x-text="completedCount() + ' / ' + totalCount()"></span>
                        </div>

                        {{-- Objectives List --}}
                        <div class="flex flex-col gap-1">
                            <template x-for="(obj, idx) in allObjectives()" :key="idx">
                                <div class="flex items-start gap-2 py-1.5 px-2 rounded" style="background-color: #13151a;">
                                    <span class="text-sm font-mono shrink-0 mt-0.5" :class="objectiveClass(obj)" x-text="objectiveIcon(obj)"></span>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-xs font-mono" :class="objectiveDescClass(obj)" x-text="obj.description"></div>
                                        <template x-if="obj.type === 'REMAIN_UNDETECTED' && obj.threshold != null">
                                            <div class="text-xs text-gray-600 mt-0.5 font-mono" x-text="'threshold: &lt; ' + obj.threshold + '% exposure'"></div>
                                        </template>
                                    </div>
                                    <span
                                        class="text-xs font-mono px-1.5 py-0.5 rounded shrink-0"
                                        style="background-color: #0c0d10; border: 1px solid #2a2d36;"
                                        :class="objectiveClass(obj)"
                                        x-text="formatObjectiveType(obj.type)"
                                    ></span>
                                </div>
                            </template>
                        </div>

                        {{-- Required Files Section --}}
                        <template x-if="activeMission && activeMission.objectives && activeMission.objectives.filter(o => o.type === 'EXFILTRATE_FILE').length > 0">
                            <div class="mt-4">
                                <div class="text-xs text-gray-500 mb-2 tracking-wider font-mono">REQUIRED FILES</div>
                                <div class="flex flex-col gap-1">
                                    <template x-for="obj in activeMission.objectives.filter(o => o.type === 'EXFILTRATE_FILE')" :key="obj.id">
                                        <div class="flex items-center gap-2 py-1.5 px-2 rounded" style="background-color: #13151a;">
                                            <span x-text="objectiveIcon(obj)" :class="objectiveClass(obj)" class="text-sm font-mono shrink-0"></span>
                                            <code class="text-cyan-400 text-xs font-mono flex-1" x-text="obj.target"></code>
                                            <template x-if="obj.completed">
                                                <span class="text-green-400 text-xs font-mono shrink-0">✓ DELIVERED</span>
                                            </template>
                                            <template x-if="!obj.completed">
                                                <span class="text-yellow-500 text-xs font-mono shrink-0">⧗ PENDING</span>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        {{-- Bonus Objectives --}}
                        <template x-if="bonusObjectives().length > 0">
                            <div>
                                <div class="text-xs os-text-muted font-mono mb-2">BONUS</div>
                                <div class="flex flex-col gap-1">
                                    <template x-for="(obj, idx) in bonusObjectives()" :key="idx">
                                        <div class="flex items-start gap-2 py-1.5 px-2 rounded border border-dashed" style="background-color: #13151a; border-color: #2a2d36;">
                                            <span class="text-sm font-mono shrink-0 mt-0.5 text-yellow-500" x-text="objectiveIcon(obj)"></span>
                                            <div class="flex-1 min-w-0">
                                                <div class="text-xs font-mono os-text-dim" x-text="obj.description"></div>
                                            </div>
                                            <span
                                                class="text-xs font-mono px-1.5 py-0.5 rounded text-yellow-600 shrink-0"
                                                style="background-color: #0c0d10; border: 1px solid #2a2d36;"
                                                x-text="formatObjectiveType(obj.type)"
                                            ></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        {{-- Status Bar --}}
                        <div class="flex items-center gap-4 pt-2 border-t text-xs font-mono" style="border-color: #2a2d36;">
                            <div class="flex items-center gap-1">
                                <span class="os-text-muted">PEAK EXP</span>
                                <span class="text-yellow-400" x-text="(activeMission.peakExposure || 0) + '%'"></span>
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="os-text-muted">DETECTIONS</span>
                                <span :class="(activeMission.detectionCount || 0) > 0 ? 'text-red-400' : 'text-green-400'" x-text="activeMission.detectionCount || 0"></span>
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="os-text-muted">STEALTH</span>
                                <span :class="activeMission.stealthViolated ? 'text-red-400' : 'text-green-400'" x-text="activeMission.stealthViolated ? 'BLOWN' : 'CLEAN'"></span>
                            </div>
                        </div>

                    </div>
                </template>
            </div>
        </template>

        {{-- BOARD TAB --}}
        <template x-if="tab === 'board'">
            <div class="p-4">
                <template x-if="isLoading">
                    <div class="flex items-center justify-center h-32">
                        <span class="text-xs os-text-muted font-mono animate-pulse">LOADING BOARD...</span>
                    </div>
                </template>

                {{-- Empty State --}}
                <template x-if="!isLoading && availableJobs.length === 0">
                    <div class="text-center py-12 os-text-muted">
                        <div class="text-lg font-mono">[SYSTEM] NO ENCRYPTED SIGNALS DETECTED</div>
                        <div class="text-xs mt-2">Complete objectives to unlock new jobs</div>
                    </div>
                </template>

                {{-- Mission List --}}
                <template x-if="!isLoading && availableJobs.length > 0">
                    <div class="flex flex-col gap-3">
                        <template x-for="(job, idx) in availableJobs" :key="idx">
                            <div class="rounded border cursor-pointer"
                                 :class="selectedJobId === job.id ? 'border-cyan-700' : ''"
                                 style="background-color: #13151a; border-color: #2a2d36;"
                                 @click="selectMission(job.id)">

                                {{-- Mission Header (always visible) --}}
                                <div class="flex items-start justify-between gap-2 p-3">
                                    <div>
                                        <div class="text-sm font-medium font-mono os-text" x-text="job.title"></div>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="text-xs font-mono" :class="factionColor(job.contact)" x-text="'[' + (job.contact || 'unknown') + ']'"></span>
                                        </div>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <div class="text-sm font-mono text-yellow-400" x-text="'§' + (job.baseReward || 0)"></div>
                                        <div class="text-xs font-mono mt-0.5" :class="difficultyClass(job.difficulty || 1)" x-text="difficultyLabel(job.difficulty || 1)"></div>
                                    </div>
                                </div>

                                {{-- Expanded section (only if selected) --}}
                                <template x-if="selectedJobId === job.id">
                                    <div class="border-t px-3 pb-3 pt-2" style="border-color: #2a2d36;">

                                        {{-- Decryption Overlay (if not decrypted) --}}
                                        <template x-if="!job.decrypted">
                                            <div class="mt-4 p-6 bg-black/50 border border-blue-500/30 rounded">
                                                <template x-if="isDecrypting(job.id)">
                                                    {{-- Decrypting Animation --}}
                                                    <div>
                                                        <div class="text-center mb-4 text-blue-400 font-mono animate-pulse">
                                                            [DECRYPTING SIGNAL...]
                                                        </div>

                                                        {{-- Progress Bar --}}
                                                        <div class="w-full bg-gray-800 h-2 rounded overflow-hidden">
                                                            <div class="bg-blue-500 h-full transition-all duration-100"
                                                                 :style="`width: ${getDecryptionProgress(job.id)}%`"></div>
                                                        </div>

                                                        {{-- Random Hex Noise (visual flair) --}}
                                                        <div class="mt-4 font-mono text-xs text-green-500/30 leading-relaxed overflow-hidden h-20"
                                                             x-text="generateHexNoise()"></div>
                                                    </div>
                                                </template>

                                                <template x-if="!isDecrypting(job.id)">
                                                    {{-- Not Started Yet --}}
                                                    <div class="text-center text-blue-400 font-mono">
                                                        [ENCRYPTED - CLICK TO DECRYPT]
                                                    </div>
                                                </template>
                                            </div>
                                        </template>

                                        {{-- Mission Details (only after decryption) --}}
                                        <template x-if="job.decrypted">
                                            <div class="mt-4 space-y-3">
                                                {{-- Description --}}
                                                <div class="text-xs os-text-muted mb-3" x-text="job.description"></div>

                                                {{-- Briefing --}}
                                                <template x-if="job.briefing">
                                                    <div class="text-xs font-mono os-text-dim whitespace-pre-wrap mb-3 p-2 rounded"
                                                         style="background-color: #0c0d10; border: 1px solid #1a1c23;"
                                                         x-text="job.briefing"></div>
                                                </template>

                                                {{-- Objectives --}}
                                                <template x-if="job.objectives && job.objectives.length > 0">
                                                    <div>
                                                        <h4 class="font-bold text-sm mb-2 os-text">OBJECTIVES:</h4>
                                                        <ul class="list-disc list-inside text-sm os-text-muted space-y-1">
                                                            <template x-for="obj in job.objectives">
                                                                <li x-text="obj.description"></li>
                                                            </template>
                                                        </ul>
                                                    </div>
                                                </template>

                                                {{-- Meta row --}}
                                                <div class="flex items-center gap-3 text-xs font-mono os-text-muted mb-3">
                                                    <span x-text="'~' + (job.estimatedTimeMinutes || job.estimatedTime || '?') + 'min'"></span>
                                                    <span x-text="difficultyStars(job.difficulty || 1)" :class="difficultyClass(job.difficulty || 1)"></span>
                                                </div>

                                                {{-- Accept / In Progress --}}
                                                <template x-if="isJobActive(job.id)">
                                                    <div class="text-xs font-mono text-cyan-400">▶ IN PROGRESS — see ACTIVE tab</div>
                                                </template>
                                                <template x-if="!isJobActive(job.id)">
                                                    <div class="flex items-center gap-2">
                                                        <button
                                                            @click.stop="acceptMission(job.id)"
                                                            :disabled="accepting || hasActiveMission()"
                                                            class="px-3 py-1.5 rounded text-xs font-mono font-medium transition-colors"
                                                            :class="hasActiveMission()
                                                                ? 'bg-gray-700/30 text-gray-600 cursor-not-allowed'
                                                                : 'bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 hover:bg-cyan-500/30'"
                                                        >
                                                            <span x-show="!accepting">ACCEPT MISSION</span>
                                                            <span x-show="accepting" class="animate-pulse">PROCESSING...</span>
                                                        </button>
                                                        <template x-if="hasActiveMission() && !isJobActive(job.id)">
                                                            <span class="text-xs os-text-muted font-mono">Complete active job first</span>
                                                        </template>
                                                    </div>
                                                </template>

                                                {{-- Accept result feedback --}}
                                                <template x-if="acceptResult">
                                                    <div class="mt-2 text-xs font-mono"
                                                         :class="acceptResult.success ? 'text-green-400' : 'text-red-400'"
                                                         x-text="acceptResult.message"></div>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </template>

        {{-- HISTORY TAB --}}
        <template x-if="tab === 'history'">
            <div class="flex flex-col items-center justify-center h-48 gap-3 p-4">
                <svg class="w-10 h-10 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <div class="text-sm os-text-muted font-mono">Completed jobs log coming soon.</div>
                <div class="text-xs text-gray-700 font-mono">// TODO: persistent history endpoint</div>
            </div>
        </template>

    </div>

    {{-- Footer --}}
    <div class="px-3 py-2 border-t flex items-center justify-between" style="border-color: #2a2d36; background-color: #0c0d10;">
        <span class="text-xs os-text-muted font-mono">underground job market v2</span>
        <template x-if="hasActiveMission() && tab === 'active'">
            <span class="text-xs font-mono text-cyan-400/60" x-text="'auto-refresh 5s'"></span>
        </template>
    </div>

</div>
