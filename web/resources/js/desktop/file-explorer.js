/**
 * File Explorer Component - Browse and open files
 */
export function fileExplorer() {
    return {
        currentPath: '/home/user',
        files: [],
        selectedFile: null,
        isLoading: false,
        viewingFile: null,
        fileContent: '',

        init() {
            this.loadDirectory(this.currentPath);
        },

        async loadDirectory(path) {
            this.isLoading = true;
            this.selectedFile = null;
            this.viewingFile = null;

            try {
                const response = await fetch('/api/filesystem', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({ action: 'list', path }),
                });

                const result = await response.json();

                if (result.success) {
                    this.currentPath = path;
                    this.files = result.files;
                }
            } catch (error) {
                console.error('File explorer error:', error);
            } finally {
                this.isLoading = false;
            }
        },

        async openFile(file) {
            if (file.type === 'directory') {
                const newPath = this.currentPath === '/'
                    ? `/${file.name}`
                    : `${this.currentPath}/${file.name}`;
                this.loadDirectory(newPath);
                return;
            }

            // Open file
            try {
                const response = await fetch('/api/filesystem', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({
                        action: 'read',
                        path: `${this.currentPath}/${file.name}`,
                    }),
                });

                const result = await response.json();

                if (result.success) {
                    this.viewingFile = file;
                    this.fileContent = result.content;
                }
            } catch (error) {
                console.error('Error reading file:', error);
            }
        },

        navigateUp() {
            if (this.currentPath === '/') return;

            const parts = this.currentPath.split('/').filter(Boolean);
            parts.pop();
            const newPath = parts.length === 0 ? '/' : '/' + parts.join('/');
            this.loadDirectory(newPath);
        },

        closeFileView() {
            this.viewingFile = null;
            this.fileContent = '';
        },

        selectFile(file) {
            this.selectedFile = file;
        },

        handleDoubleClick(file) {
            this.openFile(file);
        },

        getIcon(file) {
            if (file.type === 'directory') {
                return `<svg class="w-8 h-8 text-[--color-accent]" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/>
                </svg>`;
            }

            const ext = file.name.split('.').pop()?.toLowerCase() || '';

            if (['txt', 'log', 'md'].includes(ext)) {
                return `<svg class="w-8 h-8 text-[--color-text-secondary]" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/>
                </svg>`;
            }

            if (['bin', 'exe', 'dat'].includes(ext)) {
                return `<svg class="w-8 h-8 text-[--color-warning]" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>`;
            }

            return `<svg class="w-8 h-8 text-[--color-text-muted]" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/>
            </svg>`;
        },

        get breadcrumbs() {
            const parts = this.currentPath.split('/').filter(Boolean);
            const crumbs = [{ name: '/', path: '/' }];

            let currentPath = '';
            for (const part of parts) {
                currentPath += '/' + part;
                crumbs.push({ name: part, path: currentPath });
            }

            return crumbs;
        },

        navigateToBreadcrumb(path) {
            this.loadDirectory(path);
        }
    };
}
