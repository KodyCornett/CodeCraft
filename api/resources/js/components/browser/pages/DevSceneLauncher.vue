<template>
    <div class="dsl-wrap">

        <!-- ── Scene list ────────────────────────────────────────────────────── -->
        <template v-if="!activeScene">
            <div class="dsl-header">
                <span class="dsl-tag">[ DEV BUILD ]</span>
                <span class="dsl-title">SCENE SPLICER</span>
                <span class="dsl-sub">// Remove splice://dev/scenes from SpliceRouter.js before release</span>
            </div>

            <div class="dsl-options-row">
                <label class="dsl-toggle">
                    <input type="checkbox" v-model="autoAdvance" />
                    <span>AUTO-ADVANCE CHOICES</span>
                </label>
                <button class="dsl-refresh-btn" :disabled="loading" @click="fetchScenes">
                    {{ loading ? '[ LOADING... ]' : '[ REFRESH ]' }}
                </button>
            </div>

            <div v-if="error" class="dsl-error">{{ error }}</div>

            <div v-for="doc in groupedDocs" :key="doc.slug" class="dsl-doc-group">
                <div class="dsl-doc-header" :style="{ '--dsl-accent': doc.config.accentColor }">
                    <span class="dsl-doc-handle">{{ doc.config.npcHandle }}</span>
                    <span class="dsl-doc-sub">{{ doc.config.npcSubtitle }}</span>
                </div>

                <div
                    v-for="scene in doc.scenes"
                    :key="scene.id"
                    class="dsl-scene"
                    :class="{ 'dsl-scene--empty': !scene.dialogue }"
                >
                    <div class="dsl-scene-header">
                        <span class="dsl-scene-title">STAGE {{ scene.stageNumber }} — {{ scene.title }}</span>
                    </div>
                    <div class="dsl-scene-badges">
                        <span v-if="scene.dialogue" class="dsl-badge dsl-badge--dialogue">
                            DIALOGUE // {{ scene.dialogue.length }} lines
                        </span>
                        <span v-if="scene.fieldComms" class="dsl-badge dsl-badge--comms">
                            FIELD COMMS // {{ scene.fieldComms.length }} lines
                        </span>
                    </div>
                    <button
                        class="dsl-play-btn"
                        :disabled="!scene.dialogue"
                        @click="playScene(doc, scene)"
                    >{{ scene.dialogue ? '[ PLAY ]' : '[ NO DIALOGUE — FIELD COMMS NOT WIRED YET ]' }}</button>
                </div>
            </div>

            <div v-if="!loading && groupedDocs.length === 0 && !error" class="dsl-empty">
                No stages with dialogue found. Run the QuestArcSeeder / QuestStageSeeder first.
            </div>
        </template>

        <!-- ── Active scene — render through the real DialoguePage component ──── -->
        <template v-else>
            <div class="dsl-playing-bar">
                <button class="dsl-back-btn" @click="closeScene">[ BACK TO SCENE LIST ]</button>
                <span class="dsl-playing-label">
                    PLAYING — {{ activeScene.docConfig.npcHandle }} / STAGE {{ activeScene.stageNumber }}
                </span>
            </div>
            <div class="dsl-stage">
                <DialoguePage
                    :key="activeScene.id"
                    :entries="activeScene.dialogue"
                    :npc-handle="activeScene.docConfig.npcHandle"
                    :npc-subtitle="activeScene.docConfig.npcSubtitle"
                    :location-label="activeScene.docConfig.locationLabel"
                    :accent-color="activeScene.docConfig.accentColor"
                    :ambient-src="activeScene.docConfig.ambientSrc"
                    :auto-advance-choices="autoAdvance"
                    @complete="closeScene"
                />
            </div>
        </template>

    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import DialoguePage from './DialoguePage.vue';
import { DOC_CONFIG } from '../../../constants/docConfig.js';
import { useDevScenes } from '../../../composables/useDevScenes.js';
import { useAudio } from '../../../composables/useAudio.js';

// ── Fixed doc display order — matches the district order used elsewhere ───────
const DOC_ORDER = ['knuckle', 'patch', 'veil', 'axiom', 'float'];

const { scenes, loading, error, fetchScenes } = useDevScenes();
const { fadeOutForDialogue, fadeInAfterDialogue } = useAudio();

const autoAdvance = ref(false);
const activeScene = ref(null);   // { id, stageNumber, dialogue, docConfig }

onMounted(fetchScenes);

// ── Group the flat scene list by doc, in a stable display order ───────────────
const groupedDocs = computed(() => {
    const bySlug = {};
    for (const scene of scenes.value) {
        (bySlug[scene.docSlug] ??= []).push(scene);
    }

    return DOC_ORDER
        .filter(slug => bySlug[slug]?.length)
        .map(slug => ({
            slug,
            config: DOC_CONFIG[slug] ?? { npcHandle: slug.toUpperCase(), npcSubtitle: '', accentColor: '#00FFC8' },
            scenes: bySlug[slug],
        }));
});

function playScene(doc, scene) {
    if (!scene.dialogue) return;
    // Temporary diagnostic — remove once voice playback is confirmed working.
    console.log(
        '[DEV SCENES] playScene()', scene.id,
        '— isArray:', Array.isArray(scene.dialogue),
        '— length:', scene.dialogue?.length,
        '— entries:', JSON.parse(JSON.stringify(scene.dialogue)),
    );
    fadeOutForDialogue();
    activeScene.value = {
        id:          scene.id,
        stageNumber: scene.stageNumber,
        dialogue:    scene.dialogue,
        docConfig:   doc.config,
    };
}

function closeScene() {
    activeScene.value = null;
    fadeInAfterDialogue();
}

// Don't leave music ducked if the dev navigates away mid-scene.
onUnmounted(() => {
    if (activeScene.value) fadeInAfterDialogue();
});
</script>

<style scoped>
.dsl-wrap {
    padding: 20px;
    font-family: 'JetBrains Mono', monospace;
    display: flex;
    flex-direction: column;
    gap: 16px;
    min-height: 100%;
    box-sizing: border-box;
}

/* ── Header ──────────────────────────────────────────────────────────────── */

.dsl-header {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(0,255,100,0.12);
}

.dsl-tag   { font-size: 9px;  color: #ff3333; letter-spacing: 0.2em; }
.dsl-title { font-size: 14px; color: #00ff9d; letter-spacing: 0.15em; font-weight: 700; }
.dsl-sub   { font-size: 8px;  color: rgba(0,255,100,0.2); letter-spacing: 0.1em; }

/* ── Options row ─────────────────────────────────────────────────────────── */

.dsl-options-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.dsl-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 9px;
    letter-spacing: 0.15em;
    color: rgba(0,255,100,0.6);
    cursor: pointer;
}

.dsl-refresh-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.15em;
    background: transparent;
    border: 1px solid rgba(0,255,100,0.2);
    color: rgba(0,255,100,0.5);
    padding: 5px 14px;
    cursor: pointer;
}

.dsl-refresh-btn:hover:not(:disabled) {
    border-color: #00ff9d;
    color: #00ff9d;
}

.dsl-error {
    font-size: 10px;
    color: #ff6666;
    letter-spacing: 0.08em;
}

.dsl-empty {
    font-size: 10px;
    color: rgba(0,255,100,0.35);
    letter-spacing: 0.08em;
}

/* ── Doc groups ──────────────────────────────────────────────────────────── */

.dsl-doc-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.dsl-doc-header {
    display: flex;
    align-items: baseline;
    gap: 10px;
    padding: 6px 0;
    border-bottom: 1px solid var(--dsl-accent, #00ff9d);
}

.dsl-doc-handle { font-size: 12px; font-weight: 700; letter-spacing: 0.1em; color: var(--dsl-accent, #00ff9d); }
.dsl-doc-sub    { font-size: 8px;  letter-spacing: 0.1em; color: rgba(255,255,255,0.35); }

/* ── Scene cards ─────────────────────────────────────────────────────────── */

.dsl-scene {
    border: 1px solid rgba(0,255,100,0.15);
    padding: 12px 16px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.dsl-scene--empty {
    border-color: rgba(0,255,100,0.07);
    opacity: 0.5;
}

.dsl-scene-title { font-size: 10px; font-weight: 700; color: #00ff9d; letter-spacing: 0.08em; }

.dsl-scene-badges {
    display: flex;
    gap: 8px;
}

.dsl-badge {
    font-size: 8px;
    letter-spacing: 0.1em;
    padding: 2px 6px;
    border: 1px solid currentColor;
}

.dsl-badge--dialogue { color: rgba(0,255,100,0.6); }
.dsl-badge--comms    { color: rgba(255,200,0,0.6); }

.dsl-play-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.15em;
    background: transparent;
    border: 1px solid rgba(0,255,100,0.3);
    color: rgba(0,255,100,0.6);
    padding: 7px 0;
    cursor: pointer;
    margin-top: 4px;
}

.dsl-play-btn:hover:not(:disabled) {
    background: rgba(0,255,100,0.06);
    border-color: #00ff9d;
    color: #00ff9d;
}

.dsl-play-btn:disabled {
    border-color: rgba(0,255,100,0.08);
    color: rgba(0,255,100,0.2);
    cursor: not-allowed;
}

/* ── Active scene playback ───────────────────────────────────────────────── */

.dsl-playing-bar {
    display: flex;
    align-items: center;
    gap: 16px;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(0,255,100,0.12);
}

.dsl-back-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.15em;
    background: transparent;
    border: 1px solid rgba(0,255,100,0.3);
    color: rgba(0,255,100,0.6);
    padding: 6px 12px;
    cursor: pointer;
}

.dsl-back-btn:hover {
    border-color: #00ff9d;
    color: #00ff9d;
}

.dsl-playing-label {
    font-size: 9px;
    letter-spacing: 0.1em;
    color: rgba(0,255,100,0.4);
}

.dsl-stage {
    flex: 1;
    min-height: 480px;
    border: 1px solid rgba(0,255,100,0.12);
}
</style>
