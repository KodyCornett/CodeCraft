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
        filter: 'all', // 'all', 'unread', 'jobs', 'contacts'

        // Compose form
        composeTo: '',
        composeSubject: '',
        composeBody: '',
        replyToId: null,

        init() {
            this.loadMessages();
            this.loadContacts();
        },

        async loadMessages() {
            this.isLoading = true;
            try {
                const response = await fetch('/api/messages');
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
            if (this.filter === 'jobs') return this.messages.filter(m => m.type === 'job');
            if (this.filter === 'contacts') return this.messages.filter(m => m.type === 'contact');
            return this.messages;
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
                .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                .replace(/\n/g, '<br>');
        }
    };
}
