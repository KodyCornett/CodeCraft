import { generateCorrelatedSet } from './dataFeed.js';
import { evaluate, describeTarget, describeOutcome } from './rules/correlateTraceRule.js';
import { selectCommands } from './commandPalette.js';

for (let pairCount = 3; pairCount <= 6; pairCount++) {
    const artifacts = generateCorrelatedSet({ pairCount });
    console.log(`\n=== pairCount=${pairCount} (artifacts=${artifacts.length}) ===`);

    // sanity: exactly one correlationMismatch true
    const mismatches = artifacts.filter(a => a.correlationMismatch);
    console.log('mismatch count:', mismatches.length, mismatches.map(a => a.id));

    // print each artifact's text so a human can eyeball the correlation signal
    artifacts
        .slice()
        .sort((a, b) => a.sessionId.localeCompare(b.sessionId) || a.role.localeCompare(b.role))
        .forEach(a => console.log(`[${a.sessionId}/${a.role}${a.correlationMismatch ? ' <-- MISMATCH' : ''}]`, a.text.replace(/\n/g, ' | ')));

    const content = { artifacts, theme: { noun: 'record', nounPlural: 'records', valueLabel: 'CHAIN INTEGRITY' } };
    console.log('hint:', describeTarget(content));

    const trueId = mismatches[0].id;
    const win = evaluate([trueId], content);
    const lose = evaluate([artifacts.find(a => !a.correlationMismatch).id], content);
    console.log('win check success:', win.success, describeOutcome(win, content));
    console.log('lose check success:', lose.success, describeOutcome(lose, content));

    const commands = selectCommands(['cert', 'log'], 2);
    console.log('commands offered:', commands.map(c => c.key));
}
