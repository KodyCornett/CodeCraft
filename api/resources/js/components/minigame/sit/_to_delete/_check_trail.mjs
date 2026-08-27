import { runCommand } from './fsInterpreter.js';
import { buildProofScenario } from './proofScenario.js';

const scenario = buildProofScenario();
let state = { cwd: [], solved: false };
function run(line) {
    const r = runCommand(scenario.root, state, line, scenario);
    state = r.state;
    console.log(`> ${line}`);
    r.output.forEach(l => console.log('  ' + l));
}

run('cat readme.txt');
run('cd home/s.walker');
run('cat notes.txt');
run('cd mail');
run('cat inbox.eml');
run('cat sent.eml');
run('cd /home/k.ramos');
run('cat notes.txt');
run('cd /var/logs');
run('cat deploy.log');
run('login s.walker THRESHOLD19');
console.log('\n=== solved:', state.solved, '===');
