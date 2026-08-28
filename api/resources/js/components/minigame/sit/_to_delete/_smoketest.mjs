import { runCommand, getSuggestions, resolveSegments } from './fsInterpreter.js';
import { buildVpnCredentialsScenario } from './scenarios/vpnCredentials.js';
import { buildRelayIpScenario } from './scenarios/relayIp.js';
import { SCENARIOS, buildScenario, DEFAULT_SCENARIO_KEY } from './scenarios/index.js';

function assert(cond, msg) {
    if (!cond) throw new Error('FAIL: ' + msg);
    console.log('  ok - ' + msg);
}

function run(root, state, scenario, line) {
    return runCommand(root, state, line, scenario);
}

console.log('== vpnCredentials walkthrough ==');
{
    const scenario = buildVpnCredentialsScenario();
    let state = { cwd: [], solved: false };
    let r;

    r = run(scenario.root, state, scenario, 'cat readme.txt'); state = r.state;
    assert(r.output.some(l => l.includes('S.WALKER')), 'readme names S.WALKER as target');

    r = run(scenario.root, state, scenario, 'cd home/s.walker'); state = r.state;
    r = run(scenario.root, state, scenario, 'cat notes.txt'); state = r.state;
    assert(r.output.some(l => l.includes('k.ramos')), 'notes.txt points at k.ramos');

    r = run(scenario.root, state, scenario, 'cd mail'); state = r.state;
    r = run(scenario.root, state, scenario, 'cat sent.eml'); state = r.state;
    assert(r.output.some(l => l.includes('deploy logs')), 'sent.eml points at deploy logs');

    r = run(scenario.root, state, scenario, 'cd /home/k.ramos'); state = r.state;
    r = run(scenario.root, state, scenario, 'cat notes.txt'); state = r.state;
    assert(r.output.some(l => l.includes('THRESHOLD')), 'k.ramos notes give codename THRESHOLD');

    r = run(scenario.root, state, scenario, 'cd /var/logs'); state = r.state;
    r = run(scenario.root, state, scenario, 'cat deploy.log'); state = r.state;
    assert(r.output.some(l => l.includes('2019')), 'deploy.log gives ship year 2019');

    r = run(scenario.root, state, scenario, 'login s.walker THRESHOLD19'); state = r.state;
    assert(state.solved === true, 'login with THRESHOLD19 solves it');
    assert(r.output.includes('ACCESS GRANTED.'), 'login success message present');

    // wrong creds
    let state2 = { cwd: [], solved: false };
    r = run(scenario.root, state2, scenario, 'login s.walker WRONGPASS');
    assert(r.state.solved === false, 'wrong password does not solve');

    // help lists the scenario verb
    r = run(scenario.root, state2, scenario, 'help');
    assert(r.output.some(l => l.includes('login <user> <pass>')), 'help lists login usage');

    // suggestions never leak login args
    const s1 = getSuggestions(scenario.root, state2, 'login s.walker THR', scenario);
    assert(s1.length === 0, 'no suggestions for login password argument');
    const s2 = getSuggestions(scenario.root, state2, '', scenario);
    assert(s2.includes('login') && s2.includes('ls') && s2.includes('cd') && s2.includes('cat') && s2.includes('help'), 'empty-input suggestions include login + universals');
}

console.log('== relayIp walkthrough ==');
{
    const scenario = buildRelayIpScenario();
    let state = { cwd: [], solved: false };
    let r;

    r = run(scenario.root, state, scenario, 'cat readme.txt'); state = r.state;
    assert(r.output.some(l => l.includes('connect <ip>')), 'readme explains connect verb');

    r = run(scenario.root, state, scenario, 'cd home/netops'); state = r.state;
    r = run(scenario.root, state, scenario, 'cat handoff.txt'); state = r.state;
    assert(r.output.some(l => l.includes('10.44')), 'handoff.txt gives 10.44.x.x range');
    assert(r.output.some(l => l.includes('ticket_4821')), 'handoff.txt points at ticket_4821');

    r = run(scenario.root, state, scenario, 'cd /home/deskside'); state = r.state;
    r = run(scenario.root, state, scenario, 'cat ticket_4821.txt'); state = r.state;
    assert(r.output.some(l => l.includes('10.44.19.x')), 'ticket gives third octet subnet 10.44.19.x');
    assert(r.output.join(' ').includes('10.44.6.12') && r.output.join(' ').toLowerCase().includes('decommissioned'), 'ticket labels decoy IP as decommissioned');
    assert(r.output.some(l => l.includes('audit log')), 'ticket points at audit log');

    r = run(scenario.root, state, scenario, 'cd /home/secops'); state = r.state;
    r = run(scenario.root, state, scenario, 'cat audit.log'); state = r.state;
    assert(r.output.some(l => l.includes('10.44.19.231') && l.includes('ALLOWED')), 'audit log shows allowed connection to full target IP');

    // decoy IP fails
    let stateDecoy = { cwd: [], solved: false };
    r = run(scenario.root, stateDecoy, scenario, 'connect 10.44.6.12');
    assert(r.state.solved === false, 'decoy IP does not solve it');

    // correct IP solves it
    r = run(scenario.root, state, scenario, 'connect 10.44.19.231'); state = r.state;
    assert(state.solved === true, 'connect with correct full IP solves it');
    assert(r.output.includes('CONNECTION ESTABLISHED.'), 'connect success message present');

    // help lists the scenario verb
    let stateHelp = { cwd: [], solved: false };
    r = run(scenario.root, stateHelp, scenario, 'help');
    assert(r.output.some(l => l.includes('connect <ip>')), 'help lists connect usage');

    // suggestions never leak connect's ip argument
    const s1 = getSuggestions(scenario.root, stateHelp, 'connect 10.44.', scenario);
    assert(s1.length === 0, 'no suggestions for connect ip argument');
    const s2 = getSuggestions(scenario.root, stateHelp, '', scenario);
    assert(s2.includes('connect'), 'empty-input suggestions include connect');
    assert(!s2.includes('login'), 'relayIp suggestions do not include vpn scenario verb login (scenario-scoped)');
}

console.log('== registry ==');
{
    assert(Object.keys(SCENARIOS).length === 2, 'registry has exactly 2 scenarios');
    assert(typeof SCENARIOS.vpn_credentials.label === 'string', 'vpn_credentials has a label');
    assert(typeof SCENARIOS.relay_ip.label === 'string', 'relay_ip has a label');
    const built = buildScenario('relay_ip');
    assert(built.verbs.connect, 'buildScenario("relay_ip") returns relay scenario');
    const fallback = buildScenario('nonexistent_key');
    assert(fallback.verbs.login, 'buildScenario falls back to default (vpn_credentials) for unknown key');
    assert(DEFAULT_SCENARIO_KEY === 'vpn_credentials', 'default key is vpn_credentials');
}

console.log('== unrecognized/no-scenario safety ==');
{
    // runCommand with no scenario at all shouldn't throw
    const scenario = buildVpnCredentialsScenario();
    const r = runCommand(scenario.root, { cwd: [], solved: false }, 'ls', undefined);
    assert(Array.isArray(r.output), 'runCommand with undefined scenario still works for universal verbs');
    const r2 = runCommand(scenario.root, { cwd: [], solved: false }, 'login x y', undefined);
    assert(r2.output[0].includes('command not recognized'), 'unknown verb with no scenario reports not recognized, does not throw');
}

console.log('\nALL SMOKE TESTS PASSED');
