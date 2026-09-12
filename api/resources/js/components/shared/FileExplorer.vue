<template>
    <div class="explorer">
        <!-- Toolbar — back button + breadcrumb path -->
        <div class="exp-toolbar">
            <button
                v-if="viewingFile || breadcrumb.length > 0"
                class="exp-back"
                @click="goBack"
            >‹ BACK</button>

            <div class="exp-path">
                <template v-for="(crumb, i) in breadcrumbWithRoot" :key="crumb.id ?? 'root'">
                    <span
                        class="exp-crumb"
                        :class="{ 'exp-crumb--link': !viewingFile && i < breadcrumbWithRoot.length - 1 }"
                        @click="!viewingFile && i < breadcrumbWithRoot.length - 1 && jumpTo(i)"
                    >{{ crumb.name }}</span>
                    <span v-if="i < breadcrumbWithRoot.length - 1 || viewingFile" class="exp-sep">/</span>
                </template>
                <span v-if="viewingFile" class="exp-crumb exp-crumb--current">{{ viewingFile.name }}.{{ viewingFile.extension }}</span>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading || fileLoading" class="exp-loading">// READING DISK...</div>

        <!-- File viewer -->
        <div v-else-if="viewingFile" class="exp-viewer">
            <pre class="exp-viewer-content">{{ viewingFile.content }}</pre>
        </div>

        <!-- Folder contents -->
        <div v-else class="exp-grid">
            <button
                v-for="item in currentChildren"
                :key="item.id"
                class="exp-item"
                @click="openItem(item)"
            >
                <span class="exp-item-icon">{{ item.type === 'folder' ? '▢' : '▤' }}</span>
                <span class="exp-item-label">{{ item.name }}<span v-if="item.extension">.{{ item.extension }}</span></span>
            </button>
            <div v-if="currentChildren.length === 0" class="exp-empty">EMPTY FOLDER</div>
        </div>
    </div>
</template>

<script setup>
// FileExplorer — Phase 1 (read-only, see CONTRACTS_AND_OS_REWORK_PLAN.md).
// A real per-player virtual file system, not a hardcoded doc list, so a
// future mission can drop a file into a specific player's tree without
// touching this component. Single click opens (folder navigates in, file
// shows its content) — matches the single-click convention the rest of the
// OS shell already uses (Desktop icons, Start Menu items).
import { ref, computed, onMounted } from 'vue';
import { useFileSystem } from '@/composables/useFileSystem.js';

const { loading, fetchTree, fetchFileContent, childrenOf } = useFileSystem();

const currentFolderId = ref(null);   // null = top level ("THIS PC")
const breadcrumb      = ref([]);     // [{ id, name }, ...] folders navigated into, root excluded
const viewingFile     = ref(null);   // { id, name, extension, content } | null
const fileLoading     = ref(false);

onMounted(fetchTree);

const currentChildren    = computed(() => childrenOf(currentFolderId.value));
const breadcrumbWithRoot = computed(() => [{ id: null, name: 'THIS PC' }, ...breadcrumb.value]);

async function openItem(item) {
    if (item.type === 'folder') {
        currentFolderId.value = item.id;
        breadcrumb.value = [...breadcrumb.value, { id: item.id, name: item.name }];
        return;
    }

    fileLoading.value = true;
    try {
        viewingFile.value = await fetchFileContent(item.id);
    } catch (e) {
        console.warn('[FILES] failed to open file:', e?.message);
    } finally {
        fileLoading.value = false;
    }
}

function goBack() {
    if (viewingFile.value) {
        viewingFile.value = null;
        return;
    }
    if (breadcrumb.value.length === 0) return;
    breadcrumb.value = breadcrumb.value.slice(0, -1);
    currentFolderId.value = breadcrumb.value.length
        ? breadcrumb.value[breadcrumb.value.length - 1].id
        : null;
}

/** Jump to a breadcrumb segment — index 0 is always root ("THIS PC"). */
function jumpTo(i) {
    if (i === 0) {
        currentFolderId.value = null;
        breadcrumb.value = [];
        return;
    }
    breadcrumb.value = breadcrumb.value.slice(0, i);
    currentFolderId.value = breadcrumb.value[breadcrumb.value.length - 1].id;
}
</script>

<style scoped>
.explorer {
    display: flex;
    flex-direction: column;
    height: 100%;
    font-family: 'JetBrains Mono', monospace;
}

/* ── Toolbar ──────────────────────────────────────────────────────────────── */
.exp-toolbar {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 10px 16px;
    border-bottom: 1px solid rgba(0, 255, 255, 0.08);
    flex-shrink: 0;
}

.exp-back {
    background: transparent;
    border: 1px solid rgba(0, 255, 255, 0.2);
    color: rgba(0, 255, 255, 0.7);
    font-family: inherit;
    font-size: 9px;
    letter-spacing: 0.08em;
    padding: 4px 10px;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.12s, color 0.12s;
}
.exp-back:hover { background: rgba(0, 255, 255, 0.08); color: #00FFFF; }

.exp-path {
    font-size: 10px;
    letter-spacing: 0.06em;
    color: rgba(0, 255, 255, 0.4);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.exp-crumb--link { cursor: pointer; }
.exp-crumb--link:hover { color: #00FFFF; }
.exp-crumb--current { color: rgba(0, 255, 255, 0.8); }
.exp-sep { margin: 0 6px; color: rgba(0, 255, 255, 0.2); }

/* ── Loading / empty states ───────────────────────────────────────────────── */
.exp-loading,
.exp-empty {
    padding: 24px 16px;
    font-size: 10px;
    letter-spacing: 0.1em;
    color: rgba(0, 255, 255, 0.3);
    text-align: center;
}

/* ── Folder grid ──────────────────────────────────────────────────────────── */
.exp-grid {
    flex: 1;
    min-height: 0;
    overflow: auto;
    display: flex;
    flex-wrap: wrap;
    align-content: flex-start;
    gap: 18px;
    padding: 20px;
}

.exp-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    width: 84px;
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 8px 4px;
    border-radius: 2px;
    transition: background 0.12s;
}
.exp-item:hover { background: rgba(0, 255, 255, 0.06); }

.exp-item-icon {
    font-size: 26px;
    line-height: 1;
    color: rgba(0, 255, 255, 0.75);
}

.exp-item-label {
    font-size: 9px;
    letter-spacing: 0.05em;
    text-align: center;
    color: rgba(0, 255, 255, 0.7);
    word-break: break-word;
}
.exp-item:hover .exp-item-icon,
.exp-item:hover .exp-item-label { color: #00FFFF; }

/* ── File viewer ──────────────────────────────────────────────────────────── */
.exp-viewer {
    flex: 1;
    min-height: 0;
    overflow: auto;
    padding: 20px 24px;
}

.exp-viewer-content {
    font-family: inherit;
    font-size: 12px;
    line-height: 1.6;
    color: rgba(0, 255, 255, 0.85);
    white-space: pre-wrap;
    word-break: break-word;
    margin: 0;
}
</style>
