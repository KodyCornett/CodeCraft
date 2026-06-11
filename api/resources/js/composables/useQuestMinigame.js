/**
 * useQuestMinigame
 *
 * Module-level singleton that bridges QuestLog (inside InGameBrowser) and Game.vue.
 *
 * QuestLog calls launch() when the player clicks [INITIATE HACK].
 * Game.vue watches activeMinigame, closes the browser, and renders the overlay.
 * Game.vue also calls setCurrentNode() on every move so QuestLog can gate the button.
 */

import { ref } from 'vue';

const activeMinigame      = ref(null);   // { stageId, gameType, skin } | null
const currentNodeCanvasId = ref(null);   // canvas_id of the player's current node

export function useQuestMinigame() {

    function launch(stageId, gameType, skin) {
        activeMinigame.value = { stageId, gameType, skin };
    }

    function clear() {
        activeMinigame.value = null;
    }

    function setCurrentNode(canvasId) {
        currentNodeCanvasId.value = canvasId;
    }

    return { activeMinigame, currentNodeCanvasId, launch, clear, setCurrentNode };
}
