<template>
  <FloatingTerminalWindow
    v-if="currentStep"
    :target="currentStep.target"
    :title="currentStep.title"
    :placement="currentStep.placement ?? 'auto'"
    :window-width="340"
    :dismissable="false"
    :visible="true"
  >

    <!-- ── Phase 1: topbar ───────────────────────────────────────────────── -->
    <div v-if="currentStep.id === 'ph1-topbar'" class="pht-content">
      <p class="pht-body">
        <span class="pht-em">Packet Hijack</span> is PvP combat in CodeCraft.
        You and your opponent are both attacking each other's system at the same time —
        first to steal from the other's wallet wins.
      </p>
      <div class="pht-rule">// THE THREE PHASES</div>
      <div class="pht-table">
        <div class="pht-trow">
          <span class="pht-tcell pht-tcell--key">PHASE 1</span>
          <span class="pht-tcell">RECON HUNT — find your opponent's IP from a grid of suspects.</span>
        </div>
        <div class="pht-trow">
          <span class="pht-tcell pht-tcell--key">PHASE 2</span>
          <span class="pht-tcell">EXPLOIT CHAIN — crack their ports in the right order to breach.</span>
        </div>
        <div class="pht-trow">
          <span class="pht-tcell pht-tcell--key">PHASE 3</span>
          <span class="pht-tcell">BANK ACCESS — navigate the filesystem, find the wallet, transfer.</span>
        </div>
      </div>
      <p class="pht-body pht-body--note">
        This is a <span class="pht-em">practice run</span> — no opponent, no consequences.
        Play at your own pace.
      </p>
    </div>

    <!-- ── Phase 1: suspect board ────────────────────────────────────────── -->
    <div v-else-if="currentStep.id === 'ph1-data-zone'" class="pht-content">
      <p class="pht-body">
        Your opponent's rig is hiding among these suspect IPs.
        Use recon commands to narrow it down, then <span class="pht-em">inject</span>
        the correct IP to advance to Phase 2.
      </p>
      <div class="pht-rule">// HOW TO NARROW IT DOWN</div>
      <div class="pht-table">
        <div class="pht-trow">
          <span class="pht-tcell pht-tcell--key">ping</span>
          <span class="pht-tcell">Fast response = active player on the node.</span>
        </div>
        <div class="pht-trow">
          <span class="pht-tcell pht-tcell--key">traceroute</span>
          <span class="pht-tcell">Low hop count = same local network segment.</span>
        </div>
        <div class="pht-trow">
          <span class="pht-tcell pht-tcell--key">arp --scan</span>
          <span class="pht-tcell">Last-active timestamps. Target arrived recently.</span>
        </div>
        <div class="pht-trow">
          <span class="pht-tcell pht-tcell--key">sniff</span>
          <span class="pht-tcell">Intercepts one octet of the real target IP.</span>
        </div>
        <div class="pht-trow">
          <span class="pht-tcell pht-tcell--key">flush &lt;ip&gt;</span>
          <span class="pht-tcell">Remove a confirmed decoy to clean up the board.</span>
        </div>
      </div>
    </div>

    <!-- ── Phase 1: CMD ref ──────────────────────────────────────────────── -->
    <div v-else-if="currentStep.id === 'ph1-ref'" class="pht-content">
      <p class="pht-body">
        The command reference is always visible on the right.
        It updates each phase to show only the commands available to you right now.
      </p>
      <div class="pht-rule">// RIG COMMANDS</div>
      <p class="pht-body">
        If you have <span class="pht-em">hack-context commands</span> equipped in your loadout,
        they appear below the CMD REF as one-use buttons.
        They apply effects inside Packet Hijack — things like locking the opponent's
        input or corrupting their port board.
      </p>
    </div>

    <!-- ── Phase 1: terminal ─────────────────────────────────────────────── -->
    <div v-else-if="currentStep.id === 'ph1-terminal'" class="pht-content">
      <p class="pht-body">
        Type commands here and press <span class="pht-em">Enter</span> to execute.
        A dropdown autocompletes commands and arguments — use
        <span class="pht-em">↑↓</span> to navigate and <span class="pht-em">Space</span> to select.
      </p>
      <div class="pht-rule">// START HERE</div>
      <p class="pht-body">
        Run <span class="pht-em">netstat --active</span> to populate the suspect board
        and begin your trace. The output above the terminal updates as you gather intel.
      </p>
      <div class="pht-rule">// WRONG INJECT = LOCKOUT</div>
      <p class="pht-body">
        Committing the wrong IP with <span class="pht-em">inject</span> locks your terminal
        for several seconds. Confirm with recon before you commit.
      </p>
    </div>

    <!-- ── Phase 2: port board ───────────────────────────────────────────── -->
    <div v-else-if="currentStep.id === 'ph2-data-zone'" class="pht-content">
      <p class="pht-body">
        Phase 2. You've locked the target IP — now crack their port chain.
        Each card here is an open port on their system.
        <span class="pht-em">probe</span> a port to read its banner and look for anomalies.
      </p>
      <div class="pht-rule">// THE EXPLOIT CHAIN</div>
      <p class="pht-body">
        A hidden chain of 2–3 ports links to the exfil port <span class="pht-em">8080</span>.
        Use <span class="pht-em">trace &lt;p1&gt; &lt;p2&gt;</span> to test adjacency hypotheses.
        Once you know the order, <span class="pht-em">exploit</span> each port in sequence,
        then <span class="pht-em">breach &lt;ip&gt;</span> to open the auth prompt.
      </p>
      <div class="pht-rule">// PORT STATES</div>
      <div class="pht-table">
        <div class="pht-trow">
          <span class="pht-tcell pht-tcell--key">PROBED</span>
          <span class="pht-tcell">Banner read — anomaly visible.</span>
        </div>
        <div class="pht-trow">
          <span class="pht-tcell pht-tcell--key">CONFIRMED</span>
          <span class="pht-tcell">Chain link confirmed via trace.</span>
        </div>
        <div class="pht-trow">
          <span class="pht-tcell pht-tcell--key">SHATTERED</span>
          <span class="pht-tcell">Successfully exploited.</span>
        </div>
      </div>
    </div>

    <!-- ── Phase 2: cred strip ───────────────────────────────────────────── -->
    <div v-else-if="currentStep.id === 'ph2-cred-strip'" class="pht-content">
      <p class="pht-body">
        As you exploit chain ports, credential fragments are revealed here —
        hostname prefix and OS prefix fill in progressively.
        Once the full chain is shattered and both are complete,
        <span class="pht-em">breach &lt;ip&gt;</span> assembles them into login credentials.
      </p>
      <div class="pht-rule">// TRACE ATTEMPTS</div>
      <p class="pht-body">
        The counter on the right is your remaining <span class="pht-em">trace budget</span>.
        Each <span class="pht-em">trace</span> command consumes one attempt whether it confirms
        adjacency or not. Your CPU stat sets your starting budget.
        When it hits zero, you can no longer test hypotheses — exploit by deduction.
      </p>
    </div>

    <!-- ── Phase 2: CMD ref ──────────────────────────────────────────────── -->
    <div v-else-if="currentStep.id === 'ph2-ref'" class="pht-content">
      <p class="pht-body">
        The CMD REF has updated to show Phase 2 commands.
        You're ready — work through the port board, crack the chain,
        and breach the system.
      </p>
      <div class="pht-rule">// PHASE 3 PREVIEW</div>
      <p class="pht-body">
        Once authenticated, the bank interface loads automatically.
        Navigate the filesystem with <span class="pht-em">ls</span> and <span class="pht-em">cd</span>,
        find the <span class="pht-em">wallet</span> file, and hit
        <span class="pht-em">[ XFER FUNDS ]</span> to complete the breach.
        In a real match — that transfer ends the fight.
      </p>
    </div>

    <!-- ── Footer nav ─────────────────────────────────────────────────────── -->
    <template #footer>
      <div class="pht-footer">
        <button class="pht-skip" @click="onSkip">[ skip ]</button>
        <div class="pht-nav">
          <span class="pht-count">{{ stepNumber }} / {{ totalSteps }}</span>
          <button class="pht-next" @click="onNext">
            {{ isLast ? '[ GOT IT ]' : '[ NEXT ]' }}
          </button>
        </div>
      </div>
    </template>

  </FloatingTerminalWindow>
</template>

<script setup>
import FloatingTerminalWindow  from '@/components/shared/FloatingTerminalWindow.vue';
import { usePacketHijackTour } from '@/composables/usePacketHijackTour.js';

const emit = defineEmits(['done']);

const { currentStep, stepNumber, isLast, totalSteps, next, skip } = usePacketHijackTour();

function onNext() {
    if (isLast.value) {
        skip();
        emit('done');
    } else {
        next();
    }
}

function onSkip() {
    skip();
    emit('done');
}
</script>

<style scoped>
.pht-content {
    padding: 14px 16px 12px;
    display: flex;
    flex-direction: column;
    gap: 0;
}

.pht-rule {
    font-size: 8px;
    letter-spacing: 0.16em;
    color: rgba(255, 179, 0, 0.55);
    margin: 10px 0 5px;
}

.pht-body {
    margin: 0 0 4px;
    font-size: 11px;
    line-height: 1.65;
    color: rgba(255, 255, 255, 0.88);
}

.pht-body--note {
    margin-top: 10px;
    padding: 6px 10px;
    background: rgba(255, 179, 0, 0.05);
    border-left: 2px solid rgba(255, 179, 0, 0.3);
}

.pht-em {
    color: #FFB300;
    font-style: normal;
}

.pht-table {
    display: flex;
    flex-direction: column;
    gap: 2px;
    margin-bottom: 4px;
}

.pht-trow {
    display: flex;
    gap: 8px;
    padding: 4px 8px;
    background: rgba(255, 255, 255, 0.03);
    border-left: 2px solid rgba(255, 179, 0, 0.15);
    font-size: 10px;
    line-height: 1.5;
    color: rgba(255, 255, 255, 0.82);
}

.pht-tcell      { flex: 1; }
.pht-tcell--key {
    flex: 0 0 auto;
    width: 90px;
    color: rgba(255, 179, 0, 0.80);
    letter-spacing: 0.04em;
    font-size: 9px;
}

/* Footer */
.pht-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 14px;
}

.pht-skip {
    background: transparent;
    border: none;
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    color: rgba(255, 255, 255, 0.25);
    cursor: pointer;
    padding: 0;
    letter-spacing: 0.08em;
    transition: color 0.12s;
}
.pht-skip:hover { color: rgba(255, 255, 255, 0.55); }

.pht-nav {
    display: flex;
    align-items: center;
    gap: 12px;
}

.pht-count {
    font-size: 9px;
    color: rgba(255, 179, 0, 0.35);
    letter-spacing: 0.1em;
}

.pht-next {
    background: transparent;
    border: 1px solid rgba(255, 179, 0, 0.45);
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.12em;
    color: rgba(255, 179, 0, 0.85);
    cursor: pointer;
    padding: 4px 10px;
    transition: border-color 0.12s, color 0.12s, background 0.12s;
}
.pht-next:hover {
    border-color: rgba(255, 179, 0, 0.9);
    color: #FFB300;
    background: rgba(255, 179, 0, 0.06);
}
</style>
