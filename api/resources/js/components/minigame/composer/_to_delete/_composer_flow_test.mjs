import { generateArtifactSet } from './dataFeed.js';
import { selectCommands } from './commandPalette.js';

for (let tier = 1; tier <= 4; tier++) {
    const count = 3 + tier;
    const artifacts = generateArtifactSet({ kind: 'mixed', count, flawedCount: 1, hostname: 'auth.example-corp.net' });
    const kindsPresent = [...new Set(artifacts.map(a => a.kind))];
    const commands = selectCommands(kindsPresent, 1 + Math.floor(tier / 2));

    console.log(`\n=== TIER ${tier} (count=${count}) ===`);
    console.log('kinds present:', kindsPresent);
    console.log('commands offered:', commands.map(c => `${c.key}${c.revealsKind ? '->' + c.revealsKind : ' (decoy)'}`));
    const flawed = artifacts.filter(a => a.flawed);
    console.log('flawed count:', flawed.length, 'flawType(s):', flawed.map(a => a.flawType));
    console.log('sample artifact kinds in set:', artifacts.map(a => a.kind).join(','));
}
