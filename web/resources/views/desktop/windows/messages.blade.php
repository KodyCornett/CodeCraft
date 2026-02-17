<div x-data="messages()" class="h-full flex flex-col os-window">
    {{-- Messages Toolbar --}}
    <div class="flex items-center justify-between px-3 py-2 border-b" style="border-color: #2a2d36; background-color: #1a1c23;">
        <div class="flex items-center gap-2">
            {{-- Back button (when reading/composing) --}}
            <button
                x-show="view !== 'inbox'"
                @click="backToInbox()"
                class="p-1.5 rounded os-bg-hover os-text-dim transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>

            {{-- View title --}}
            <span class="text-sm font-medium os-text">
                <template x-if="view === 'inbox'">
                    <span>Inbox</span>
                </template>
                <template x-if="view === 'read'">
                    <span>Message</span>
                </template>
                <template x-if="view === 'compose'">
                    <span x-text="replyToId ? 'Reply' : 'New Message'"></span>
                </template>
            </span>

            {{-- Unread badge --}}
            <span
                x-show="unreadCount > 0 && view === 'inbox'"
                class="px-1.5 py-0.5 text-xs rounded-full bg-cyan-600 text-white"
                x-text="unreadCount"
            ></span>
        </div>

        <div class="flex items-center gap-2">
            {{-- Compose button --}}
            <button
                x-show="view === 'inbox'"
                @click="startCompose()"
                class="px-3 py-1.5 rounded text-sm os-accent border border-cyan-700 os-bg-hover transition-colors flex items-center gap-1"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Compose
            </button>

            {{-- Refresh --}}
            <button
                x-show="view === 'inbox'"
                @click="loadMessages()"
                class="p-1.5 rounded os-bg-hover os-text-dim transition-colors"
                :class="isLoading ? 'animate-spin' : ''"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Content Area --}}
    <div class="flex-1 flex overflow-hidden">
        {{-- Inbox View --}}
        <template x-if="view === 'inbox'">
            <div class="flex-1 flex flex-col">
                {{-- Filters --}}
                <div class="flex items-center gap-2 px-3 py-2 border-b" style="border-color: #2a2d36;">
                    <button
                        @click="changeFilter('all')"
                        class="px-2 py-1 text-xs rounded transition-colors"
                        :class="filter === 'all' ? 'os-bg-active os-accent' : 'os-text-dim os-bg-hover'"
                    >All</button>
                    <button
                        @click="changeFilter('unread')"
                        class="px-2 py-1 text-xs rounded transition-colors"
                        :class="filter === 'unread' ? 'os-bg-active os-accent' : 'os-text-dim os-bg-hover'"
                    >Unread</button>
                    <button
                        @click="changeFilter('contacts')"
                        class="px-2 py-1 text-xs rounded transition-colors"
                        :class="filter === 'contacts' ? 'os-bg-active os-accent' : 'os-text-dim os-bg-hover'"
                    >Contacts</button>
                    <button
                        @click="changeFilter('trash')"
                        class="px-2 py-1 text-xs rounded transition-colors flex items-center gap-1"
                        :class="filter === 'trash' ? 'os-bg-active os-accent' : 'os-text-dim os-bg-hover'"
                    >
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Trash
                    </button>
                </div>

                {{-- Message List --}}
                <div class="flex-1 overflow-y-auto">
                    {{-- Loading --}}
                    <div x-show="isLoading" class="flex items-center justify-center h-32">
                        <div class="text-sm os-text-dim">Loading messages...</div>
                    </div>

                    {{-- Empty State --}}
                    <div x-show="!isLoading && filteredMessages.length === 0" class="flex items-center justify-center h-32">
                        <div class="text-center">
                            <div class="text-3xl mb-2">📭</div>
                            <div class="text-sm os-text-dim">No messages</div>
                        </div>
                    </div>

                    {{-- Messages --}}
                    <template x-for="message in filteredMessages" :key="message.id">
                        <button
                            @click="openMessage(message)"
                            class="w-full px-3 py-3 flex items-start gap-3 border-b transition-colors text-left"
                            :class="message.read ? 'os-bg-hover' : 'os-bg-active'"
                            style="border-color: #2a2d36;"
                        >
                            {{-- Avatar --}}
                            <div class="text-2xl flex-shrink-0" x-text="message.avatar"></div>

                            {{-- Content --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-2 mb-1">
                                    <span
                                        class="text-sm font-medium truncate"
                                        :class="message.read ? 'os-text-dim' : 'os-text'"
                                        x-text="message.from"
                                    ></span>
                                    <span class="text-xs os-text-muted flex-shrink-0" x-text="message.timestamp"></span>
                                </div>
                                <div
                                    class="text-sm truncate mb-1"
                                    :class="message.read ? 'os-text-dim' : 'os-text'"
                                    x-text="message.subject"
                                ></div>
                                <div class="text-xs os-text-muted truncate" x-text="message.preview"></div>
                            </div>

                            {{-- Type indicator --}}
                            <div class="flex flex-col items-end gap-1 flex-shrink-0">
                                <span
                                    x-show="!message.read"
                                    class="w-2 h-2 rounded-full bg-cyan-500"
                                ></span>
                                <span
                                    x-show="message.type === 'job'"
                                    class="px-1.5 py-0.5 text-xs rounded bg-green-600/20 text-green-400"
                                >JOB</span>
                            </div>
                        </button>
                    </template>
                </div>
            </div>
        </template>

        {{-- Read Message View --}}
        <template x-if="view === 'read' && selectedMessage">
            <div class="flex-1 flex flex-col overflow-hidden">
                {{-- Message Header --}}
                <div class="px-4 py-3 border-b" style="border-color: #2a2d36;">
                    <div class="flex items-center gap-3 mb-2">
                        <span class="text-3xl" x-text="selectedMessage.avatar"></span>
                        <div>
                            <div class="font-medium os-text" x-text="selectedMessage.from"></div>
                            <div class="text-xs os-text-muted" x-text="selectedMessage.timestamp"></div>
                        </div>
                    </div>
                    <h2 class="text-lg os-text" x-text="selectedMessage.subject"></h2>
                </div>

                {{-- Message Body --}}
                <div class="flex-1 overflow-y-auto p-4" @click="handleBodyClick($event)">
                    {{-- Encrypted message with passcode input --}}
                    <template x-if="selectedMessage.encrypted">
                        <div class="text-center space-y-4 py-8">
                            <div class="text-6xl">&#x1F512;</div>
                            <div class="os-accent font-medium">ENCRYPTED MESSAGE</div>
                            <p class="os-text-dim text-sm">This message is protected. Enter passcode to decrypt.</p>

                            <div class="max-w-xs mx-auto space-y-3">
                                <input
                                    x-model="passcodeInput"
                                    @keydown.enter="decryptMessage()"
                                    type="text"
                                    class="w-full p-3 bg-black/50 border border-cyan-800 rounded os-text text-center uppercase font-mono tracking-widest"
                                    placeholder="PASSCODE"
                                    autocomplete="off"
                                >

                                <div x-show="decryptError" class="text-red-400 text-sm" x-text="decryptError"></div>

                                <button
                                    @click="decryptMessage()"
                                    :disabled="!passcodeInput.trim() || isDecrypting"
                                    class="w-full px-4 py-2 bg-cyan-600 hover:bg-cyan-500 rounded text-sm transition-colors font-medium disabled:opacity-50"
                                >
                                    <span x-show="!isDecrypting">Decrypt</span>
                                    <span x-show="isDecrypting">Decrypting...</span>
                                </button>
                            </div>

                            <p class="text-xs os-text-muted mt-4">Hint: The passcode was revealed when you accepted the job.</p>
                        </div>
                    </template>

                    {{-- Normal message body --}}
                    <template x-if="!selectedMessage.encrypted">
                        <div class="message-body os-text text-sm leading-relaxed" x-html="formatBody(selectedMessage.body)"></div>
                    </template>

                    {{-- Job Details Card (for non-encrypted or decrypted messages with job data) --}}
                    <template x-if="!selectedMessage.encrypted && selectedMessage.jobData">
                        <div class="mt-4 p-4 rounded-lg border" style="background-color: #13151a; border-color: #2a2d36;">
                            <h3 class="text-sm font-medium os-accent mb-3">Mission Details</h3>
                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <span class="os-text-muted">Target:</span>
                                    <span class="os-text font-mono ml-2" x-text="selectedMessage.jobData.target"></span>
                                </div>
                                <div>
                                    <span class="os-text-muted">Payout:</span>
                                    <span class="text-green-400 ml-2">&sect;<span x-text="selectedMessage.jobData.payout?.toLocaleString()"></span></span>
                                </div>
                                <div>
                                    <span class="os-text-muted">Objective:</span>
                                    <span class="os-text ml-2" x-text="selectedMessage.jobData.objective"></span>
                                </div>
                                <div>
                                    <span class="os-text-muted">Difficulty:</span>
                                    <span
                                        class="ml-2 px-2 py-0.5 rounded text-xs"
                                        :class="{
                                            'bg-green-600/20 text-green-400': selectedMessage.jobData.difficulty === 'easy',
                                            'bg-yellow-600/20 text-yellow-400': selectedMessage.jobData.difficulty === 'medium',
                                            'bg-red-600/20 text-red-400': selectedMessage.jobData.difficulty === 'hard',
                                            'bg-purple-600/20 text-purple-400': selectedMessage.jobData.difficulty === 'extreme'
                                        }"
                                        x-text="selectedMessage.jobData.difficulty"
                                    ></span>
                                </div>
                            </div>

                            {{-- Quick Start Guide --}}
                            <div class="mt-4 pt-3 border-t" style="border-color: #2a2d36;">
                                <h4 class="text-xs font-medium os-accent mb-2">Quick Start</h4>
                                <div class="space-y-2 text-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="os-text-muted w-4">1.</span>
                                        <code class="os-accent font-mono bg-black/30 px-2 py-0.5 rounded">scan <span x-text="selectedMessage.jobData.target"></span></code>
                                        <span class="os-text-dim">- Find open ports</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="os-text-muted w-4">2.</span>
                                        <code class="os-accent font-mono bg-black/30 px-2 py-0.5 rounded">connect <span x-text="selectedMessage.jobData.target"></span></code>
                                        <span class="os-text-dim">- Connect to target</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="os-text-muted w-4">3.</span>
                                        <code class="os-accent font-mono bg-black/30 px-2 py-0.5 rounded">ls</code>
                                        <span class="os-text-dim">- Explore files</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="os-text-muted w-4">4.</span>
                                        <code class="os-accent font-mono bg-black/30 px-2 py-0.5 rounded">download &lt;file&gt;</code>
                                        <span class="os-text-dim">- Get the objective</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Actions: Reply, Delete, Restore --}}
                <div class="px-4 py-3 border-t flex items-center justify-between" style="border-color: #2a2d36;">
                    {{-- Reply Button --}}
                    <div x-show="selectedMessage.replyable">
                        <button
                            @click="startReply()"
                            class="px-4 py-2 rounded text-sm os-accent border border-cyan-700 os-bg-hover transition-colors flex items-center gap-2"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                            </svg>
                            Reply
                        </button>
                    </div>

                    {{-- Delete/Restore Buttons --}}
                    <div class="flex items-center gap-2">
                        {{-- Restore (only in trash) --}}
                        <button
                            x-show="isInTrash()"
                            @click="restoreMessage()"
                            :disabled="isDeleting"
                            class="px-4 py-2 rounded text-sm transition-colors flex items-center gap-2 border border-green-700 text-green-400 hover:bg-green-900/20"
                            :class="isDeleting ? 'opacity-50 cursor-not-allowed' : ''"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                            </svg>
                            <span x-show="!isDeleting">Restore</span>
                            <span x-show="isDeleting">Restoring...</span>
                        </button>

                        {{-- Delete (only in inbox) --}}
                        <button
                            x-show="!isInTrash()"
                            @click="deleteMessage()"
                            :disabled="isDeleting"
                            class="px-4 py-2 rounded text-sm transition-colors flex items-center gap-2 border border-red-700 text-red-400 hover:bg-red-900/20"
                            :class="isDeleting ? 'opacity-50 cursor-not-allowed' : ''"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            <span x-show="!isDeleting">Delete</span>
                            <span x-show="isDeleting">Deleting...</span>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        {{-- Compose View --}}
        <template x-if="view === 'compose'">
            <div class="flex-1 flex flex-col overflow-hidden">
                {{-- Compose Form --}}
                <div class="flex-1 overflow-y-auto p-4 space-y-4">
                    {{-- To --}}
                    <div>
                        <label class="block text-xs os-text-muted mb-1">To</label>
                        <select
                            x-model="composeTo"
                            class="w-full px-3 py-2 rounded text-sm os-text border"
                            style="background-color: #0c0d10; border-color: #2a2d36;"
                        >
                            <option value="">Select contact...</option>
                            <template x-for="contact in contacts" :key="contact.id">
                                <option :value="contact.id" x-text="contact.avatar + ' ' + contact.name"></option>
                            </template>
                        </select>
                    </div>

                    {{-- Subject --}}
                    <div>
                        <label class="block text-xs os-text-muted mb-1">Subject</label>
                        <input
                            type="text"
                            x-model="composeSubject"
                            class="w-full px-3 py-2 rounded text-sm os-text border bg-transparent"
                            style="background-color: #0c0d10; border-color: #2a2d36;"
                            placeholder="Enter subject..."
                        >
                    </div>

                    {{-- Body --}}
                    <div class="flex-1">
                        <label class="block text-xs os-text-muted mb-1">Message</label>
                        <textarea
                            x-model="composeBody"
                            class="w-full h-48 px-3 py-2 rounded text-sm os-text border resize-none"
                            style="background-color: #0c0d10; border-color: #2a2d36;"
                            placeholder="Write your message..."
                        ></textarea>
                    </div>
                </div>

                {{-- Send Button --}}
                <div class="px-4 py-3 border-t flex justify-end gap-2" style="border-color: #2a2d36;">
                    <button
                        @click="backToInbox()"
                        class="px-4 py-2 rounded text-sm os-text-dim os-bg-hover transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        @click="sendMessage()"
                        :disabled="!composeTo || !composeSubject || !composeBody"
                        class="px-4 py-2 rounded text-sm transition-colors flex items-center gap-2"
                        :class="composeTo && composeSubject && composeBody ? 'bg-cyan-600 text-white hover:bg-cyan-500' : 'bg-gray-700 text-gray-500 cursor-not-allowed'"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                        Send
                    </button>
                </div>
            </div>
        </template>
    </div>
</div>
