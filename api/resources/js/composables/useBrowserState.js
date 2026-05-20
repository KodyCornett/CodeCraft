/**
 * useBrowserState
 *
 * Manages the in-game SPLICE browser overlay.
 * Tracks which URL is open (null = closed) and exposes
 * launch helpers used by the NavBar and node interactions.
 */

import { ref } from 'vue';
import { SPLICE } from '@/components/browser/SpliceRouter.js';

export function useBrowserState() {

    // null = browser closed. Any SPLICE url = browser open on that page.
    const activeBrowserUrl = ref(null);

    function onLaunch(url) {
        activeBrowserUrl.value = url;
    }

    function onOpenStore() {
        activeBrowserUrl.value = SPLICE.CYBER_DOC;
    }

    function onCloseBrowser() {
        activeBrowserUrl.value = null;
    }

    return { activeBrowserUrl, onLaunch, onOpenStore, onCloseBrowser };
}
