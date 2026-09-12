/**
 * useFileSystem
 *
 * Backs the File Explorer OS program. Phase 1 is read-only (see
 * CONTRACTS_AND_OS_REWORK_PLAN.md) — fetchTree() pulls the player's whole
 * file tree as a flat list (folders + files, keyed by parentId), and
 * fetchFileContent() lazily loads one file's body when it's opened.
 *
 * Module-level singleton, same pattern as useCodex/useQuestArchive, so the
 * tree only needs fetching once even if multiple components read it.
 */
import { ref, readonly } from 'vue';
import axios from 'axios';

const files  = ref([]);   // flat list: { id, parentId, name, type, extension }
const loading = ref(false);
const error   = ref(null);

export function useFileSystem() {
    async function fetchTree() {
        try {
            loading.value = true;
            error.value   = null;
            const res = await axios.get('/api/files');
            files.value = res.data.files ?? [];
        } catch (e) {
            error.value = e?.response?.data?.message ?? e.message ?? 'File system unavailable';
            console.warn('[FILES] fetch failed:', error.value);
        } finally {
            loading.value = false;
        }
    }

    /** Fetch one file's content on demand — not cached, files stay small for now. */
    async function fetchFileContent(fileId) {
        const res = await axios.get(`/api/files/${encodeURIComponent(fileId)}`);
        return res.data;
    }

    /** Direct children of a folder — pass null for the root level. */
    function childrenOf(parentId) {
        return files.value.filter(f => f.parentId === parentId);
    }

    function findById(fileId) {
        return files.value.find(f => f.id === fileId) ?? null;
    }

    return {
        files:   readonly(files),
        loading: readonly(loading),
        error:   readonly(error),
        fetchTree,
        fetchFileContent,
        childrenOf,
        findById,
    };
}
