
import { ref } from 'vue';
import { useDevSignalLock } from '@/composables/useDevSignalLock.js';

const { launch } = useDevSignalLock();

const selectedIce = ref(3);

// Pair STARTER with a high ICE selection above to test the under-geared
// (CPU-below-ICE) penalty path — no separate preset needed for that, since
// it's the ICE/rig combination that matters, not the rig alone.
const RIG_PRESETS = [
    { label: 'STARTER',  cpu: 3, ram: 2, os: 2 },
    { label: 'MID-TIER', cpu: 5, ram: 4, os: 4 },
    { label: 'HIGH-SEC', cpu: 8, ram: 6, os: 6 },
];

const selectedRig = ref(RIG_PRESETS[0]);

function onLaunch() {
    launch({ ice: selectedIce.value, cpu: selectedRig.value.cpu, ram: selectedRig.value.ram, os: selectedRig.value.os });
}
