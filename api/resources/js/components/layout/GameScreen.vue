<template>
    <!-- Outer bezel — charcoal frame suggesting a physical monitor -->
    <div class="bezel">
        <!-- Inner screen surface -->
        <div class="screen">
            <!-- Scanline texture overlay — purely atmospheric, no interaction -->
            <div class="scanlines" aria-hidden="true" />

            <!-- Neon edge glow on screen border -->
            <div class="screen-edge-glow" aria-hidden="true" />

            <!-- Game content -->
            <div class="screen-content">
                <slot />
            </div>
        </div>
    </div>
</template>

<script setup>
</script>

<style scoped>
.bezel {
    width: 100vw;
    height: 100vh;
    background: #111111;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 6px;
    box-sizing: border-box;
    /* Subtle depth shadow suggesting a physical housing */
    box-shadow:
        inset 0 2px 8px rgba(0,0,0,0.9),
        inset 0 -2px 8px rgba(0,0,0,0.9);
}

.screen {
    position: relative;
    width: 100%;
    height: 100%;
    background: #050505;
    overflow: hidden;
    /* Inset shadow — screen depth */
    box-shadow:
        inset 0 0 40px rgba(0,0,0,0.8),
        inset 0 0 4px rgba(0, 255, 255, 0.08);
}

/* Thin neon cyan border on the inner screen edge */
.screen-edge-glow {
    position: absolute;
    inset: 0;
    border: 1px solid rgba(0, 255, 255, 0.3);
    pointer-events: none;
    z-index: 10;
}

/* Scanline overlay — repeating horizontal lines at ~8% opacity */
.scanlines {
    position: absolute;
    inset: 0;
    background: repeating-linear-gradient(
        to bottom,
        transparent 0px,
        transparent 3px,
        rgba(0, 0, 0, 0.08) 3px,
        rgba(0, 0, 0, 0.08) 4px
    );
    pointer-events: none;
    z-index: 9;
}

.screen-content {
    position: relative;
    width: 100%;
    height: 100%;
    z-index: 1;
    display: flex;
    flex-direction: column;
}
</style>
