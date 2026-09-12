/**
 * useFileSystem
 *
 * Backs the File Explorer OS program (see CONTRACTS_AND_OS_REWORK_PLAN.md).
 * fetchTree() pulls the player's whole file tree as a flat list (folders +
 * files, keyed by parentId), fetchFileContent() lazily loads one file's
 * body when it's opened, and createFile()/deleteFile() cover the only two
 * mutations the player has: adding a file inside a folder, and deleting a
 * file they (or a game system) added. `protected` items — the seeded
 * Documents/Downloads folders and the starter docs — refuse both server-side;
 * there's no move/reorganize feature at all.
 *
 * Module-level singleton, same pattern as useCodex/useQuestArchive, so the
 * tree only needs fetching once even if multiple components read it.
 */
import { ref, readonly } from 'vue';
import axios from 'axios';

const files  = ref([]);   // flat list: { id, parentId, name, type, extension, protected }
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

    /**
     * Creates a file inside parentId (must be a folder the player owns).
     * Returns the new file's summary on success, or null on failure (server
     * refused — bad parent) — updates the local tree in place so the caller
     * doesn't need to refetch. Throws only on a genuine network/server error.
     */
    async function createFile(parentId, name, extension = null) {
        try {
            const res = await axios.post('/api/files', { parent_id: parentId, name, extension });
            files.value = [...files.value, res.data];
            return res.data;
        } catch (e) {
            error.value = e?.response?.data?.message ?? e.message ?? 'Could not create file';
            console.warn('[FILES] create failed:', error.value);
            return null;
        }
    }

    /**
     * Deletes a file the player owns. Returns true on success; false if the
     * server refused (protected, a folder, or not found) — updates the
     * local tree in place on success.
     */
    async function deleteFile(fileId) {
        try {
            await axios.delete(`/api/files/${encodeURIComponent(fileId)}`);
            files.value = files.value.filter(f => f.id !== fileId);
            return true;
        } catch (e) {
            error.value = e?.response?.data?.message ?? e.message ?? 'Could not delete file';
            console.warn('[FILES] delete failed:', error.value);
            return false;
        }
    }

    return {
        files:   readonly(files),
        loading: readonly(loading),
        error:   readonly(error),
        fetchTree,
        fetchFileContent,
        childrenOf,
        findById,
        createFile,
        deleteFile,
    };
}
