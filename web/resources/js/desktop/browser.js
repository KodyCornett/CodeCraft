/**
 * Browser Component - In-game web browser
 */
export function browser() {
    return {
        currentUrl: '',
        currentDomain: '',
        currentPath: '/',
        siteName: '',
        siteIcon: '',
        pageTitle: '',
        pageContent: '',
        navigation: [],
        bookmarks: [],
        history: [],
        historyIndex: -1,
        isLoading: false,
        error: null,
        showBookmarks: true,

        init() {
            this.loadBookmarks();
        },

        async loadBookmarks() {
            try {
                const response = await fetch('/api/browser/bookmarks');
                const data = await response.json();
                if (data.success) {
                    this.bookmarks = data.bookmarks;
                }
            } catch (e) {
                console.error('Failed to load bookmarks:', e);
            }
        },

        async navigate(url) {
            if (!url || this.isLoading) return;

            // Clean URL
            url = url.trim().toLowerCase();
            if (!url.includes('/')) {
                url = url + '/';
            }

            this.isLoading = true;
            this.error = null;
            this.showBookmarks = false;

            try {
                const response = await fetch('/api/browser/navigate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({ url }),
                });

                const data = await response.json();

                if (data.success) {
                    // Update state
                    this.currentDomain = data.domain;
                    this.currentPath = data.path;
                    this.currentUrl = data.domain + data.path;
                    this.siteName = data.siteName;
                    this.siteIcon = data.siteIcon;
                    this.pageTitle = data.title;
                    this.pageContent = data.content;
                    this.navigation = data.navigation || [];

                    // Add to history
                    if (this.historyIndex < this.history.length - 1) {
                        this.history = this.history.slice(0, this.historyIndex + 1);
                    }
                    this.history.push(this.currentUrl);
                    this.historyIndex = this.history.length - 1;
                } else {
                    this.error = data.message || 'Failed to load page';
                    this.pageContent = '';
                }
            } catch (e) {
                this.error = 'Connection failed';
                console.error('Browser error:', e);
            } finally {
                this.isLoading = false;
            }
        },

        goBack() {
            if (this.historyIndex > 0) {
                this.historyIndex--;
                this.navigateWithoutHistory(this.history[this.historyIndex]);
            }
        },

        goForward() {
            if (this.historyIndex < this.history.length - 1) {
                this.historyIndex++;
                this.navigateWithoutHistory(this.history[this.historyIndex]);
            }
        },

        async navigateWithoutHistory(url) {
            // Same as navigate but doesn't modify history
            this.isLoading = true;
            this.error = null;

            try {
                const response = await fetch('/api/browser/navigate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({ url }),
                });

                const data = await response.json();

                if (data.success) {
                    this.currentDomain = data.domain;
                    this.currentPath = data.path;
                    this.currentUrl = data.domain + data.path;
                    this.siteName = data.siteName;
                    this.siteIcon = data.siteIcon;
                    this.pageTitle = data.title;
                    this.pageContent = data.content;
                    this.navigation = data.navigation || [];
                    this.showBookmarks = false;
                } else {
                    this.error = data.message || 'Failed to load page';
                }
            } catch (e) {
                this.error = 'Connection failed';
            } finally {
                this.isLoading = false;
            }
        },

        goHome() {
            this.showBookmarks = true;
            this.currentUrl = '';
            this.pageContent = '';
            this.navigation = [];
            this.error = null;
        },

        refresh() {
            if (this.currentUrl) {
                this.navigateWithoutHistory(this.currentUrl);
            }
        },

        handleUrlSubmit() {
            const input = this.$refs.urlInput;
            if (input && input.value) {
                this.navigate(input.value);
            }
        },

        handleContentClick(event) {
            // Handle clicks on links within page content
            const link = event.target.closest('a[href]');
            if (link) {
                event.preventDefault();
                let href = link.getAttribute('href');

                // Handle matrix:// links
                if (href.startsWith('matrix://')) {
                    this.navigate(href.replace('matrix://', ''));
                }
                // Handle relative links
                else if (href.startsWith('/')) {
                    this.navigate(this.currentDomain + href);
                }
                // Handle absolute links
                else if (!href.startsWith('http')) {
                    this.navigate(href);
                }
            }
        },

        navigateTo(path) {
            this.navigate(this.currentDomain + path);
        },

        get canGoBack() {
            return this.historyIndex > 0;
        },

        get canGoForward() {
            return this.historyIndex < this.history.length - 1;
        }
    };
}
