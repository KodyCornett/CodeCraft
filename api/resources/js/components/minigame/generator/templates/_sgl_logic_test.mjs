// Re-implements the same pure generation logic from SignalLock.vue for
// standalone verification (the real functions live inside <script setup>
// and aren't importable directly). Any drift from the component should be
// caught by eyeballing this against the component if it's ever edited.

function randInt(min, max) { return Math.floor(min + Math.random() * (max - min + 1)); }
function pick(arr) { return arr[randInt(0, arr.length - 1)]; }
function parityOf(n) { return n % 2 === 0 ? 'EVEN' : 'ODD'; }
function genAddr(lastOctet) {
    const octet = lastOctet ?? randInt(2, 253);
    return `10.${randInt(10, 99)}.${randInt(10, 99)}.${octet}`;
}

function buildExtremalRound(count) {
    const useSignal = Math.random() < 0.5;
    const candidates = Array.from({ length: count }, (_, i) => ({
        id: i, addr: genAddr(),
        session: Math.random() < 0.55 ? 'ACTIVE' : 'IDLE',
        latency: randInt(6, 58), signal: randInt(35, 98), flag: null,
    }));
    let activeCount = candidates.filter(c => c.session === 'ACTIVE').length;
    for (const c of candidates) {
        if (activeCount >= 2) break;
        if (c.session === 'IDLE') { c.session = 'ACTIVE'; activeCount++; }
    }
    const activeOnes = candidates.filter(c => c.session === 'ACTIVE');
    let correct;
    if (useSignal) {
        correct = activeOnes.reduce((a, b) => (b.signal > a.signal ? b : a));
        activeOnes.forEach(c => { if (c !== correct && c.signal === correct.signal) c.signal -= randInt(1, 4); });
    } else {
        correct = activeOnes.reduce((a, b) => (b.latency < a.latency ? b : a));
        activeOnes.forEach(c => { if (c !== correct && c.latency === correct.latency) c.latency += randInt(1, 4); });
    }
    return { useSignal, candidates, correctIndex: candidates.indexOf(correct) };
}

function buildParityRound(count, withSpoof) {
    const candidates = Array.from({ length: count }, (_, i) => {
        const octet = randInt(2, 253);
        return { id: i, addr: genAddr(octet), octet, octetParity: parityOf(octet), checksumVal: randInt(0, 255), flag: null };
    });
    const correctIndex = randInt(0, count - 1);
    candidates.forEach((c, i) => {
        const shouldMatch = i === correctIndex;
        let val = c.checksumVal, guard = 0;
        while ((parityOf(val) === c.octetParity) !== shouldMatch && guard < 50) { val = randInt(0, 255); guard++; }
        c.checksumVal = val;
    });
    let spoofIdx = null;
    if (withSpoof) {
        const spoofPool = candidates.map((c, i) => i).filter(i => i !== correctIndex && parityOf(candidates[i].checksumVal) !== candidates[i].octetParity);
        if (spoofPool.length) {
            spoofIdx = pick(spoofPool);
            const target = candidates[spoofIdx];
            let val = target.checksumVal, guard = 0;
            while (parityOf(val) !== target.octetParity && guard < 50) { val = randInt(0, 255); guard++; }
            target.checksumVal = val;
            target.flag = 'SPOOFED';
        }
    }
    return { candidates, correctIndex, spoofIdx, withSpoof };
}

function assert(cond, msg) { if (!cond) throw new Error('FAIL: ' + msg); }

const ITER = 5000;

// ── Extremal round invariants ─────────────────────────────────────────────
for (let i = 0; i < ITER; i++) {
    const count = [4,5,6,7][randInt(0,3)];
    const r = buildExtremalRound(count);
    const activeCount = r.candidates.filter(c => c.session === 'ACTIVE').length;
    assert(activeCount >= 2, 'at least 2 active candidates');
    const correct = r.candidates[r.correctIndex];
    assert(correct.session === 'ACTIVE', 'correct candidate is ACTIVE');
    const activeOthers = r.candidates.filter((c, idx) => idx !== r.correctIndex && c.session === 'ACTIVE');
    for (const other of activeOthers) {
        if (r.useSignal) assert(other.signal < correct.signal, `no active tie/beat on signal (other=${other.signal} correct=${correct.signal})`);
        else assert(other.latency > correct.latency, `no active tie/beat on latency (other=${other.latency} correct=${correct.latency})`);
    }
}
console.log(`extremal: ${ITER} rounds OK — unique correct answer every time`);

// ── Parity round invariants (no spoof, tier 2) ────────────────────────────
for (let i = 0; i < ITER; i++) {
    const count = [4,5,6,7][randInt(0,3)];
    const r = buildParityRound(count, false);
    const matches = r.candidates.filter(c => parityOf(c.checksumVal) === c.octetParity);
    assert(matches.length === 1, `exactly one parity match, got ${matches.length}`);
    assert(r.candidates.indexOf(matches[0]) === r.correctIndex, 'the one match is the correct index');
}
console.log(`parity (no spoof): ${ITER} rounds OK — unique correct answer every time`);

// ── Parity round invariants (with spoof, tier 3+) ─────────────────────────
let spoofPresent = 0;
for (let i = 0; i < ITER; i++) {
    const count = [6,7][randInt(0,1)];
    const r = buildParityRound(count, true);
    const matches = r.candidates.filter(c => parityOf(c.checksumVal) === c.octetParity);
    if (r.spoofIdx !== null) {
        spoofPresent++;
        assert(matches.length === 2, `with a spoof placed, expected 2 parity matches, got ${matches.length}`);
        const flagged = r.candidates.find(c => c.flag === 'SPOOFED');
        assert(flagged, 'a SPOOFED candidate exists');
        assert(r.candidates.indexOf(flagged) !== r.correctIndex, 'the flagged spoof is never the correct index');
        assert(parityOf(flagged.checksumVal) === flagged.octetParity, 'the spoof genuinely matches parity (that is the whole trick)');
        const trueCorrect = r.candidates[r.correctIndex];
        assert(!trueCorrect.flag, 'the true correct candidate is never flagged');
    }
}
console.log(`parity (with spoof): ${ITER} rounds OK — spoof placed in ${spoofPresent}/${ITER} rounds, never at correctIndex, always genuinely parity-matching`);

console.log('\nALL LOGIC INVARIANTS HOLD');
