
import { useDevSIT } from '@/composables/useDevSIT.js';
import { SCENARIOS } from '@/components/minigame/sit/scenarios/index.js';

const { launch } = useDevSIT();

function onLaunch(scenarioKey) {
    launch(scenarioKey);
}
