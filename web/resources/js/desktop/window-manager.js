/**
 * Window Manager - Controls all windows on the desktop
 */
export function windowManager() {
    return {
        windows: [],
        nextZIndex: 100,
        activeWindowId: null,

        init() {
            // Open terminal by default
            this.openWindow('terminal', 'Terminal', { width: 700, height: 450, x: 50, y: 50 });
        },

        openWindow(type, title, options = {}) {
            const id = `window-${Date.now()}`;
            const defaults = {
                width: 600,
                height: 400,
                x: 100 + (this.windows.length * 30),
                y: 80 + (this.windows.length * 30),
            };

            const window = {
                id,
                type,
                title,
                width: options.width || defaults.width,
                height: options.height || defaults.height,
                x: options.x ?? defaults.x,
                y: options.y ?? defaults.y,
                zIndex: this.nextZIndex++,
                minimized: false,
                maximized: false,
                // Store original dimensions for restore
                originalBounds: null,
            };

            this.windows.push(window);
            this.focusWindow(id);

            return id;
        },

        closeWindow(id) {
            this.windows = this.windows.filter(w => w.id !== id);
            if (this.activeWindowId === id) {
                this.activeWindowId = this.windows.length > 0
                    ? this.windows[this.windows.length - 1].id
                    : null;
            }
        },

        focusWindow(id) {
            const window = this.windows.find(w => w.id === id);
            if (window) {
                window.zIndex = this.nextZIndex++;
                window.minimized = false;
                this.activeWindowId = id;
            }
        },

        minimizeWindow(id) {
            const window = this.windows.find(w => w.id === id);
            if (window) {
                window.minimized = true;
                if (this.activeWindowId === id) {
                    // Focus next non-minimized window
                    const next = [...this.windows]
                        .reverse()
                        .find(w => w.id !== id && !w.minimized);
                    this.activeWindowId = next?.id || null;
                }
            }
        },

        toggleMaximize(id) {
            const window = this.windows.find(w => w.id === id);
            if (!window) return;

            if (window.maximized) {
                // Restore
                if (window.originalBounds) {
                    window.x = window.originalBounds.x;
                    window.y = window.originalBounds.y;
                    window.width = window.originalBounds.width;
                    window.height = window.originalBounds.height;
                }
                window.maximized = false;
            } else {
                // Maximize
                window.originalBounds = {
                    x: window.x,
                    y: window.y,
                    width: window.width,
                    height: window.height,
                };
                window.x = 0;
                window.y = 0;
                window.width = window.innerWidth;
                window.height = window.innerHeight - 48; // Account for taskbar
                window.maximized = true;
            }
        },

        getWindow(id) {
            return this.windows.find(w => w.id === id);
        },

        isActive(id) {
            return this.activeWindowId === id;
        },

        // Drag handling
        startDrag(event, windowId) {
            if (event.target.closest('.window-controls')) return;

            const window = this.getWindow(windowId);
            if (!window || window.maximized) return;

            this.focusWindow(windowId);

            const startX = event.clientX;
            const startY = event.clientY;
            const startWindowX = window.x;
            const startWindowY = window.y;

            const onMouseMove = (e) => {
                window.x = startWindowX + (e.clientX - startX);
                window.y = Math.max(0, startWindowY + (e.clientY - startY));
            };

            const onMouseUp = () => {
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);
            };

            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        },

        // Resize handling
        startResize(event, windowId, direction) {
            const window = this.getWindow(windowId);
            if (!window || window.maximized) return;

            event.preventDefault();
            event.stopPropagation();

            const startX = event.clientX;
            const startY = event.clientY;
            const startWidth = window.width;
            const startHeight = window.height;
            const startWindowX = window.x;
            const startWindowY = window.y;

            const onMouseMove = (e) => {
                const dx = e.clientX - startX;
                const dy = e.clientY - startY;

                if (direction.includes('e')) {
                    window.width = Math.max(300, startWidth + dx);
                }
                if (direction.includes('w')) {
                    const newWidth = Math.max(300, startWidth - dx);
                    window.x = startWindowX + startWidth - newWidth;
                    window.width = newWidth;
                }
                if (direction.includes('s')) {
                    window.height = Math.max(200, startHeight + dy);
                }
                if (direction.includes('n')) {
                    const newHeight = Math.max(200, startHeight - dy);
                    window.y = Math.max(0, startWindowY + startHeight - newHeight);
                    window.height = newHeight;
                }
            };

            const onMouseUp = () => {
                document.removeEventListener('mousemove', onMouseMove);
                document.removeEventListener('mouseup', onMouseUp);
            };

            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        },

        // Taskbar methods
        get taskbarWindows() {
            return this.windows;
        },

        taskbarClick(id) {
            const window = this.getWindow(id);
            if (!window) return;

            if (window.minimized) {
                this.focusWindow(id);
            } else if (this.isActive(id)) {
                this.minimizeWindow(id);
            } else {
                this.focusWindow(id);
            }
        }
    };
}
