<div x-data="{
    contacts: [
        {
            id: 'ghost',
            name: 'Ghost',
            status: 'online',
            trust: 'trusted',
            specialty: 'Intel Gathering',
            lastSeen: '2 hours ago',
            notes: 'Reliable but expensive. Response time 2-4 hours.'
        },
        {
            id: 'zero',
            name: 'Zero',
            status: 'offline',
            trust: 'unverified',
            specialty: 'Network Infiltration',
            lastSeen: '3 days ago',
            notes: 'New contact. Claims expertise in network infiltration. Use with caution.'
        },
        {
            id: 'cipher',
            name: 'Cipher',
            status: 'away',
            trust: 'trusted',
            specialty: 'Cryptography',
            lastSeen: '6 hours ago',
            notes: 'Expert in encryption/decryption. Slow but thorough.'
        }
    ],
    blockedContacts: [
        {
            id: 'marcus',
            name: 'Marcus',
            reason: 'Suspected federal agent',
            lastSeen: '1 week ago'
        }
    ],
    selectedContact: null,
    showBlocked: false
}" class="h-full flex flex-col os-window">

    {{-- Toolbar --}}
    <div class="flex items-center gap-2 px-3 py-2 border-b" style="border-color: #2a2d36; background-color: #1a1c23;">
        <button
            @click="showBlocked = false"
            class="px-3 py-1 rounded text-xs font-medium transition-colors"
            :class="!showBlocked ? 'os-bg-active os-accent' : 'os-text-dim os-bg-hover'"
        >
            Trusted
        </button>
        <button
            @click="showBlocked = true"
            class="px-3 py-1 rounded text-xs font-medium transition-colors"
            :class="showBlocked ? 'bg-red-500/20 text-red-400' : 'os-text-dim os-bg-hover'"
        >
            Blocked
        </button>
    </div>

    <div class="flex-1 flex overflow-hidden">
        {{-- Contact List --}}
        <div class="w-48 border-r overflow-y-auto" style="border-color: #2a2d36;">
            <template x-if="!showBlocked">
                <div class="py-2">
                    <template x-for="contact in contacts" :key="contact.id">
                        <button
                            @click="selectedContact = contact"
                            class="w-full px-3 py-2 flex items-center gap-2 transition-colors text-left"
                            :class="selectedContact?.id === contact.id ? 'os-bg-active' : 'os-bg-hover'"
                        >
                            <div class="relative">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium" style="background-color: #2a2d36;">
                                    <span x-text="contact.name[0]"></span>
                                </div>
                                <div
                                    class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full border-2"
                                    style="border-color: #13151a;"
                                    :class="{
                                        'bg-green-500': contact.status === 'online',
                                        'bg-yellow-500': contact.status === 'away',
                                        'bg-gray-500': contact.status === 'offline'
                                    }"
                                ></div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm truncate" :class="selectedContact?.id === contact.id ? 'os-accent' : 'os-text'" x-text="contact.name"></div>
                                <div class="text-xs os-text-muted truncate" x-text="contact.specialty"></div>
                            </div>
                        </button>
                    </template>
                </div>
            </template>

            <template x-if="showBlocked">
                <div class="py-2">
                    <template x-for="contact in blockedContacts" :key="contact.id">
                        <div class="px-3 py-2 flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium bg-red-500/20 text-red-400">
                                <span x-text="contact.name[0]"></span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm text-red-400 truncate" x-text="contact.name"></div>
                                <div class="text-xs os-text-muted truncate" x-text="contact.reason"></div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        {{-- Contact Details --}}
        <div class="flex-1 p-4 overflow-y-auto">
            <template x-if="selectedContact && !showBlocked">
                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center text-lg font-medium" style="background-color: #2a2d36;">
                            <span x-text="selectedContact.name[0]"></span>
                        </div>
                        <div>
                            <div class="text-lg font-medium os-text" x-text="selectedContact.name"></div>
                            <div class="text-sm os-text-dim" x-text="selectedContact.specialty"></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <div class="text-xs os-text-muted mb-1">Status</div>
                            <div class="text-sm capitalize"
                                 :class="{
                                    'text-green-400': selectedContact.status === 'online',
                                    'text-yellow-400': selectedContact.status === 'away',
                                    'os-text-dim': selectedContact.status === 'offline'
                                 }"
                                 x-text="selectedContact.status"
                            ></div>
                        </div>
                        <div>
                            <div class="text-xs os-text-muted mb-1">Trust Level</div>
                            <div class="text-sm capitalize"
                                 :class="{
                                    'text-green-400': selectedContact.trust === 'trusted',
                                    'text-yellow-400': selectedContact.trust === 'unverified'
                                 }"
                                 x-text="selectedContact.trust"
                            ></div>
                        </div>
                        <div>
                            <div class="text-xs os-text-muted mb-1">Last Seen</div>
                            <div class="text-sm os-text-dim" x-text="selectedContact.lastSeen"></div>
                        </div>
                    </div>

                    <div>
                        <div class="text-xs os-text-muted mb-1">Notes</div>
                        <div class="text-sm os-text-dim p-3 rounded" style="background-color: #0c0d10;" x-text="selectedContact.notes"></div>
                    </div>

                    <div class="flex gap-2 pt-2">
                        <button class="flex-1 px-4 py-2 rounded text-sm font-medium os-accent border border-cyan-700 os-bg-hover transition-colors">
                            Send Message
                        </button>
                    </div>
                </div>
            </template>

            <template x-if="!selectedContact && !showBlocked">
                <div class="flex flex-col items-center justify-center h-full text-center">
                    <svg class="w-12 h-12 os-text-muted mb-3" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                    </svg>
                    <div class="text-sm os-text-muted">Select a contact</div>
                </div>
            </template>

            <template x-if="showBlocked">
                <div class="flex flex-col items-center justify-center h-full text-center">
                    <svg class="w-12 h-12 text-red-400/50 mb-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd"/>
                    </svg>
                    <div class="text-sm text-red-400/70">Blocked Contacts</div>
                    <div class="text-xs os-text-muted mt-1">Do not engage with these contacts</div>
                </div>
            </template>
        </div>
    </div>
</div>
