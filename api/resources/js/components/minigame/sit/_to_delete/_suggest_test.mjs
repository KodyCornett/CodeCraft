import { getSuggestions } from './fsInterpreter.js';
import { buildProofScenario } from './proofScenario.js';

const scenario = buildProofScenario();
const state = { cwd: [], solved: false };

function show(typed) {
    console.log(JSON.stringify(typed), '->', getSuggestions(scenario.root, state, typed));
}

show('');
show('c');
show('ca');
show('cd ');
show('cd h');
show('cd home/');
show('cd home/s');
show('cat readme');
show('login s');   // should be empty — never suggest login args
show('login s.walker THRESH'); // should be empty — must never leak password
