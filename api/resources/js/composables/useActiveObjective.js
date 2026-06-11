/**
 * useActiveObjective
 *
 * Derives the single in-progress quest stage from the full questDocs state
 * (as returned by useQuestLog). Returns the first active stage found across
 * all docs and arcs — the player always has at most one active arc at a time
 * during the prologue sequence.
 *
 * Returns null when no quest is currently in progress (tutorial not yet
 * complete, or player has finished all available arcs).
 *
 * Shape of the returned `objective` ref:
 * {
 *   stageId:       string,   // used to detect stage changes for toast triggers
 *   arcTitle:      string,
 *   stageNumber:   number,
 *   stageTitle:    string,
 *   objectiveText: string,   // server only returns this when stage is not locked
 *   docName:       string,   // e.g. "Knuckle"
 * }
 */

import { computed } from 'vue';

export function useActiveObjective(docs) {
    const objective = computed(() => {
        for (const doc of docs.value ?? []) {
            for (const arc of doc.arcs ?? []) {
                if (arc.status !== 'active') continue;

                const activeStage = (arc.stages ?? []).find(s => s.status === 'active');
                if (!activeStage) continue;

                return {
                    stageId:       activeStage.id,
                    arcTitle:      arc.title,
                    stageNumber:   activeStage.stage_number,
                    stageTitle:    activeStage.title,
                    objectiveText: activeStage.objective_text ?? null,
                    docName:       doc.name,
                };
            }
        }

        return null;
    });

    return { objective };
}
