<template>
    <template v-for="(seg, i) in segments" :key="i"><span
        v-if="seg.url"
        class="splice-inline-link"
        @click="spliceNavigate(seg.url)"
    >{{ seg.text }}</span><template v-else>{{ seg.text }}</template></template>
</template>

<script setup>
import { inject, computed } from 'vue';
import { linkifySegments } from '@/composables/textLinks.js';

const props = defineProps({
    text: { type: String, required: true },
});

const spliceNavigate = inject('spliceNavigate', () => {});
const segments = computed(() => linkifySegments(props.text));
</script>

<style scoped>
.splice-inline-link {
    text-decoration: underline;
    text-underline-offset: 2px;
    cursor: pointer;
    color: inherit;
}
.splice-inline-link:hover {
    opacity: 0.75;
}
</style>
