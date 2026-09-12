import { ref, computed } from 'vue';

/**
 * useWindowManager
 *
 * Owns ONE concern: which OS-shell programs are open, which is focused, and
 * z-order for the taskbar. Nothing here knows what a "Map" or "Browser" is —
 * callers pass an id + display meta (title/icon/accent) and this just tracks
 * open/minimized/focus state for it.
 *
 * Singleton (module-level state), same pattern as useUiTour.js / useDialogue.js —
 * the desktop, Game.vue, and the taskbar (NavBar) all need to read/mutate
 * "what's open" without threading it through unrelated props on every
 * intermediate component.
 */

// ── Singleton state ───────────────────────────────────────────────────────────
// geometry is null until the player first drags/resizes a window — until then
// OsWindow just uses its own default CSS-centered layout. Reset to null (and
// maximized to false) on every fresh open(), same as the rest of a window's
// state — closing a program and reopening it starts clean.
const _windows   = ref([]);   // [{ id, title, icon, accent, appClass, minimized, maximized, geometry, z }]
const _focusedId = ref(null);
let _zCounter = 0;

export function useWindowManager() {

    const openWindows  = computed(() => _windows.value);
    const focusedId    = computed(() => _focusedId.value);

    // Flat list for the taskbar — every open program gets one entry regardless
    // of minimized state, so it can always be clicked back open.
    const taskbarItems = computed(() =>
        _windows.value.map(w => ({
            id:        w.id,
            title:     w.title,
            icon:      w.icon,
            minimized: w.minimized,
            focused:   w.id === _focusedId.value,
        }))
    );

    function isOpen(id) {
        return _windows.value.some(w => w.id === id);
    }

    function isMinimized(id) {
        return _windows.value.find(w => w.id === id)?.minimized ?? false;
    }

    /** Stacking order for a window — bind this to an OsWindow's z-index so
     *  whichever program was focused most recently renders above the rest. */
    function zIndexOf(id) {
        return _windows.value.find(w => w.id === id)?.z ?? 0;
    }

    /** Custom position/size, or null to use OsWindow's default centered layout. */
    function geometryOf(id) {
        return _windows.value.find(w => w.id === id)?.geometry ?? null;
    }

    /** Called by OsWindow (via its 'update:geometry' emit) after a drag or resize. */
    function setGeometry(id, geometry) {
        const w = _windows.value.find(w => w.id === id);
        if (w) w.geometry = { ...geometry };
    }

    function isMaximized(id) {
        return _windows.value.find(w => w.id === id)?.maximized ?? false;
    }

    /** Traffic-light zoom button / titlebar double-click — same toggle either way. */
    function toggleMaximize(id) {
        const w = _windows.value.find(w => w.id === id);
        if (w) w.maximized = !w.maximized;
    }

    /**
     * Open a program window. If it's already open, this just restores +
     * focuses it instead of creating a second instance — there is exactly
     * one live instance per program id.
     *
     * @param {string} id    Unique program id, e.g. 'map', 'browser', 'rig'.
     * @param {object} meta  Display info: { title, icon, accent, appClass }.
     */
    function open(id, meta = {}) {
        const existing = _windows.value.find(w => w.id === id);
        if (existing) {
            Object.assign(existing, meta);
            focus(id);
            return;
        }
        _windows.value.push({ id, minimized: false, maximized: false, geometry: null, z: ++_zCounter, ...meta });
        _focusedId.value = id;
    }

    function close(id) {
        _windows.value = _windows.value.filter(w => w.id !== id);
        if (_focusedId.value === id) {
            const next = [..._windows.value].sort((a, b) => b.z - a.z)[0];
            _focusedId.value = next ? next.id : null;
        }
    }

    function minimize(id) {
        const w = _windows.value.find(w => w.id === id);
        if (w) w.minimized = true;
        if (_focusedId.value === id) _focusedId.value = null;
    }

    /** Bring a window to front, unminimizing it if needed. */
    function focus(id) {
        const w = _windows.value.find(w => w.id === id);
        if (!w) return;
        w.z = ++_zCounter;
        w.minimized = false;
        _focusedId.value = id;
    }

    /** Taskbar-click behavior: open, restore+focus, or minimize-if-already-focused. */
    function toggle(id, meta) {
        if (!isOpen(id)) { open(id, meta); return; }
        const w = _windows.value.find(w => w.id === id);
        if (w.minimized || _focusedId.value !== id) {
            focus(id);
        } else {
            minimize(id);
        }
    }

    return {
        openWindows,
        focusedId,
        taskbarItems,
        isOpen,
        isMinimized,
        zIndexOf,
        geometryOf,
        setGeometry,
        isMaximized,
        toggleMaximize,
        open,
        close,
        minimize,
        focus,
        toggle,
    };
}
