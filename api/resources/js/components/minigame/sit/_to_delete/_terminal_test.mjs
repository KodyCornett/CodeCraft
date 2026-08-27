import { runCommand } from './fsInterpreter.js';
import { buildProofScenario } from './proofScenario.js';

const scenario = buildProofScenario();
let state = { cwd: [], solved: false };

function run(line) {
    const result = runCommand(scenario.root, state, line, scenario);
    state = result.state;
    console.log(`> ${line}`);
    result.output.forEach(l => console.log('  ' + l));
    console.log('  [cwd:', '/' + state.cwd.join('/'), '| solved:', state.solved, ']');
}

run('ls');
run('cd home');
run('ls');
run('cd s.walker');
run('ls');
run('cat notes.txt');
run('cd mail');
run('ls');
run('cat inbox.eml');
run('cat sent.eml');
run('cd ../../k.ramos');
run('cat notes.txt');
run('cd /var/logs');
run('cat deploy.log');
run('login s.walker THRESHOLD19');
console.log('\n=== FINAL solved:', state.solved, '===');

// negative-path checks
let state2 = { cwd: [], solved: false };
function run2(line) {
    const result = runCommand(scenario.root, state2, line, scenario);
    state2 = result.state;
    console.log(`> ${line} =>`, result.output);
}
console.log('\n--- error handling checks ---');
run2('cd nowhere');
run2('cat missing.txt');
run2('login s.walker wrongpass');
run2('bogus_command');
run2('cat readme.txt'); // dir vs file mixups
