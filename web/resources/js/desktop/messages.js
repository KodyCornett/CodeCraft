/**
 * Messages Component - In-game messaging/email system
 */
export function messages() {
    return {
        messages: [],
        contacts: [],
        selectedMessage: null,
        unreadCount: 0,
        isLoading: false,
        view: 'inbox', // 'inbox', 'read', 'compose'
        filter: 'all', // 'all', 'unread', 'contacts', 'trash'

        // Compose form
        composeTo: '',
        composeSubject: '',
        composeBody: '',
        replyToId: null,

        // Encryption/decryption state
        passcodeInput: '',
        decryptError: null,
        isDecrypting: false,

        // Delete state
        isDeleting: false,

        init() {
            this.loadMessages();
            this.loadContacts();

            // Listen for message updates (from secure channel)
            window.addEventListener('messages-updated', () => {
                console.log('[Messages] Received messages-updated event, reloading messages...');
                this.loadMessages();
            });
        },

        async loadMessages() {
            this.isLoading = true;
            try {
                const viewParam = this.filter === 'trash' ? '?view=trash' : '';
                const response = await fetch(`/api/messages${viewParam}`);
                const data = await response.json();
                if (data.success) {
                    this.messages = data.messages;
                    this.unreadCount = data.unreadCount;
                }
            } catch (e) {
                console.error('Failed to load messages:', e);
            } finally {
                this.isLoading = false;
            }
        },

        async loadContacts() {
            try {
                const response = await fetch('/api/messages/contacts');
                const data = await response.json();
                if (data.success) {
                    this.contacts = data.contacts;
                }
            } catch (e) {
                console.error('Failed to load contacts:', e);
            }
        },

        get filteredMessages() {
            if (this.filter === 'all') return this.messages;
            if (this.filter === 'unread') return this.messages.filter(m => !m.read);
            if (this.filter === 'contacts') return this.messages.filter(m => m.type === 'contact');
            if (this.filter === 'trash') return this.messages;
            return this.messages;
        },

        // Watch filter changes to reload messages for trash view
        async changeFilter(newFilter) {
            if (this.filter !== newFilter) {
                this.filter = newFilter;
                await this.loadMessages();
            }
        },

        async openMessage(message) {
            this.selectedMessage = message;
            this.view = 'read';

            // Mark as read
            if (!message.read) {
                message.read = true;
                this.unreadCount = Math.max(0, this.unreadCount - 1);
                try {
                    await fetch(`/api/messages/${message.id}/read`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                    });
                } catch (e) {
                    console.error('Failed to mark as read:', e);
                }
            }
        },

        backToInbox() {
            this.view = 'inbox';
            this.selectedMessage = null;
            this.clearCompose();
        },

        startReply() {
            if (!this.selectedMessage || !this.selectedMessage.replyable) return;

            this.replyToId = this.selectedMessage.id;
            this.composeTo = this.selectedMessage.fromId;
            this.composeSubject = 'Re: ' + this.selectedMessage.subject;
            this.composeBody = '';
            this.view = 'compose';
        },

        startCompose(contactId = '') {
            this.clearCompose();
            this.composeTo = contactId;
            this.view = 'compose';
        },

        clearCompose() {
            this.composeTo = '';
            this.composeSubject = '';
            this.composeBody = '';
            this.replyToId = null;
        },

        async sendMessage() {
            if (!this.composeTo || !this.composeSubject || !this.composeBody) {
                return;
            }

            try {
                const response = await fetch('/api/messages/send', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({
                        to: this.composeTo,
                        subject: this.composeSubject,
                        body: this.composeBody,
                        replyTo: this.replyToId,
                    }),
                });

                const data = await response.json();
                if (data.success) {
                    this.backToInbox();
                    // Could show success notification
                }
            } catch (e) {
                console.error('Failed to send message:', e);
            }
        },

        getMessageTypeIcon(type) {
            switch (type) {
                case 'job': return '💼';
                case 'system': return '⚙️';
                case 'mysterious': return '❓';
                default: return '✉️';
            }
        },

        getMessageTypeClass(type) {
            switch (type) {
                case 'job': return 'msg-type-job';
                case 'system': return 'msg-type-system';
                case 'mysterious': return 'msg-type-mysterious';
                default: return 'msg-type-contact';
            }
        },

        formatBody(body) {
            // Convert markdown-like formatting to HTML
            return body
                // Secure channel links: [secure://channel/jobId|Link Text]
                .replace(
                    /\[secure:\/\/channel\/([^\|]+)\|([^\]]+)\]/g,
                    '<a href="#" data-secure-link data-job-id="$1" class="os-accent underline cursor-pointer hover:text-cyan-300 transition-colors">$2</a>'
                )
                .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                .replace(/\n/g, '<br>');
        },

        handleBodyClick(event) {
            // Handle secure channel link clicks
            const link = event.target.closest('a[data-secure-link]');
            if (link) {
                event.preventDefault();
                const jobId = link.dataset.jobId;

                // Dispatch event to open secure channel window
                window.dispatchEvent(new CustomEvent('open-secure-channel', {
                    detail: { jobId }
                }));
            }
        },

        async decryptMessage() {
            if (!this.passcodeInput.trim() || !this.selectedMessage) return;

            this.isDecrypting = true;
            this.decryptError = null;

            try {
                const response = await fetch(`/api/messages/${this.selectedMessage.id}/decrypt`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({
                        passcode: this.passcodeInput.toUpperCase().trim()
                    }),
                });

                const data = await response.json();

                if (data.success) {
                    // Update the message with decrypted content
                    this.selectedMessage.encrypted = false;
                    this.selectedMessage.body = data.body;
                    this.passcodeInput = '';

                    // Update in the messages array too
                    const msg = this.messages.find(m => m.id === this.selectedMessage.id);
                    if (msg) {
                        msg.encrypted = false;
                        msg.body = data.body;
                    }
                } else {
                    this.decryptError = data.error || 'Invalid passcode';
                }
            } catch (e) {
                console.error('Decryption failed:', e);
                this.decryptError = 'Decryption failed. Try again.';
            } finally {
                this.isDecrypting = false;
            }
        },

        clearDecryptState() {
            this.passcodeInput = '';
            this.decryptError = null;
        },

        async deleteMessage() {
            if (!this.selectedMessage || this.isDeleting) return;

            this.isDeleting = true;
            try {
                await fetch(`/api/messages/${this.selectedMessage.id}/delete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                });

                // Go back to inbox and reload
                this.backToInbox();
                await this.loadMessages();
            } catch (e) {
                console.error('Failed to delete message:', e);
            } finally {
                this.isDeleting = false;
            }
        },

        async restoreMessage() {
            if (!this.selectedMessage || this.isDeleting) return;

            this.isDeleting = true;
            try {
                await fetch(`/api/messages/${this.selectedMessage.id}/restore`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                });

                // Go back to inbox and reload
                this.backToInbox();
                await this.loadMessages();
            } catch (e) {
                console.error('Failed to restore message:', e);
            } finally {
                this.isDeleting = false;
            }
        },

        isInTrash() {
            return this.filter === 'trash';
        }
    };
}
