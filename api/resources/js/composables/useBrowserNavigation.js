/**
 * useBrowserNavigation
 *
 * Wraps useBrowserState with game-layer routing logic:
 *   • Intercepts TERMINAL launches — redirects to TUTORIAL until tutorial is complete.
 *   • Maps each CyberDoc hub node to its branded store URL.
 *   • Exposes onOpenStore so SidePanel can open the correct doc page per-node.
 *
 * Dependencies: tutorial (useTutorial instance), currentNodeId (ref from useMapInteraction).
 */

import { useBrowserState } from '@/composables/useBrowserState.js';
import { SPLICE }          from '@/components/browser/SpliceRouter.js';

const CYBERDOC_URLS = {
    'NS-hub': SPLICE.CYBER_DOC_PATCH,
    'BA-hub': SPLICE.CYBER_DOC_KNUCKLE,
    'DT-hub': SPLICE.CYBER_DOC_VEIL,
    'UD-hub': SPLICE.CYBER_DOC_AXIOM,
    'SV-hub': SPLICE.CYBER_DOC_FLOAT,
};

export function useBrowserNavigation({ tutorial, currentNodeId }) {
    const { activeBrowserUrl, onLaunch: _rawLaunch, onCloseBrowser } = useBrowserState();

    /**
     * Open a SPLICE URL, applying the tutorial redirect guard:
     * TERMINAL is blocked until tutorial completion — player is sent to TUTORIAL instead.
     */
    function onLaunch(url) {
        if (url === SPLICE.TERMINAL && !tutorial.allComplete.value && !tutorial.tutorialComplete.value) {
            return _rawLaunch(SPLICE.TUTORIAL);
        }
        _rawLaunch(url);
    }

    /**
     * Called by InGameBrowser's url-change emit on internal navigation.
     * Keeps activeBrowserUrl accurate so tutorial step watchers see the real current page.
     * NOTE: Must be a named function — inline template handlers auto-unwrap refs, turning
     * activeBrowserUrl into a plain string where .value assignment would be a no-op.
     */
    function onBrowserUrlChange(url) {
        console.log('%c[TUTORIAL:nav] onBrowserUrlChange →', 'color:#00FFC8;font-weight:bold', url);
        activeBrowserUrl.value = url;
    }

    /**
     * Opens the CyberDoc store page for the player's current hub node.
     * Falls back to the generic CyberDoc landing if the node ID is not a known hub.
     */
    function onOpenStore() {
        const url = CYBERDOC_URLS[currentNodeId.value] ?? SPLICE.CYBER_DOC;
        onLaunch(url);
    }

    return { activeBrowserUrl, onLaunch, onCloseBrowser, onBrowserUrlChange, onOpenStore };
}
