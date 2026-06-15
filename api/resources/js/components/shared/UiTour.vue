<template>
  <FloatingTerminalWindow
    v-if="currentStep"
    :target="currentStep.target"
    :title="currentStep.title"
    :placement="currentStep.placement ?? 'auto'"
    :window-width="340"
    :dismissable="false"
    :visible="true"
    @dismiss="skip()"
  >

    <!-- ── SPLICE stop ────────────────────────────────────────────────────── -->
    <div v-if="currentStep.id === 'splice'" class="ts-content">

      <p class="ts-body">
        <span class="ts-em">SPLICE</span> is the Frequency's internal browser —
        a hidden channel layered beneath the corporate grid.
        Every tool you need to run, upgrade, and survive lives here.
      </p>

      <div class="ts-rule">// WHAT'S INSIDE</div>
      <div class="ts-table">
        <div class="ts-trow">
          <span class="ts-tcell ts-tcell--key">◈ STATUS</span>
          <span class="ts-tcell">Full runner profile — economy, bounty run stats, active effects.</span>
        </div>
        <div class="ts-trow">
          <span class="ts-tcell ts-tcell--key">⬡ RIG</span>
          <span class="ts-tcell">Your hardware stats and upgrade state. Links to the full Stat Reference.</span>
        </div>
        <div class="ts-trow">
          <span class="ts-tcell ts-tcell--key">▶ CMDS</span>
          <span class="ts-tcell">Command catalog, loadout management, and Grid-Breach reference.</span>
        </div>
        <div class="ts-trow">
          <span class="ts-tcell ts-tcell--key">▣ INV</span>
          <span class="ts-tcell">Consumable inventory — use stims and hardware from here.</span>
        </div>
        <div class="ts-trow">
          <span class="ts-tcell ts-tcell--key">⌨ TERMINAL</span>
          <span class="ts-tcell">Mission log and active quest tracker. Check here for your next objective.</span>
        </div>
      </div>

      <div class="ts-rule">// CYBERDOC STORES</div>
      <p class="ts-body">
        Move to any <span class="ts-em">CyberDoc node</span> on the map and open SPLICE —
        the store loads automatically. Buy hardware, repair SS, and bank your pocket creds without leaving the interface.
      </p>

    </div>

    <!-- ── UPLINK stop ─────────────────────────────────────────────────────── -->
    <div v-if="currentStep.id === 'uplink'" class="ts-content">

      <!-- Live stat readout -->
      <div class="ts-live-row">
        <span class="ts-live-label">CURRENT UPLINK</span>
        <span class="ts-live-val" :class="uplinkClass">
          {{ player?.uplink ?? '—' }} / {{ player?.maxUplink ?? '—' }}
        </span>
      </div>

      <p class="ts-body">
        Every jack-in to a new node costs <span class="ts-em">1 Uplink</span>.
        When the counter hits zero, movement is blocked until you restore it.
      </p>

      <div class="ts-rule">// HOW TO RESTORE</div>
      <p class="ts-body">
        Find any node marked <span class="ts-em">⬡ UPLINK</span> on the map and breach it.
        A successful hack refills your Uplink back to maximum.
        If you're stranded at zero, you're granted <span class="ts-em">1 emergency move</span>
        so you can always reach a recovery node.
      </p>

      <div class="ts-rule">// YOUR MAXIMUM</div>
      <p class="ts-body">
        Uplink cap is <span class="ts-em">chassis-locked</span> — stat points can't raise it.
        Upgrade your chassis at a CyberDoc, or install a
        <span class="ts-em">Deep Link</span> peripheral to push the ceiling higher.
      </p>

    </div>
    <!-- ── BOUNTY stop ────────────────────────────────────────────────────── -->
    <div v-else-if="currentStep.id === 'bounty'" class="ts-content">

      <div class="ts-live-row">
        <span class="ts-live-label">CURRENT HEAT</span>
        <div class="ts-stars">
          <span
            v-for="i in 5" :key="i"
            class="ts-star"
            :class="{
              'ts-star--lit': i <= (player?.bountyLevel ?? 0),
              'ts-star--os':  i <= (player?.bountyLevel ?? 0) && player?.isOpenSeason,
            }"
          >★</span>
        </div>
      </div>

      <p class="ts-body">
        The ticker counts how many nodes you've hacked since your last CyberDoc visit.
        Stars escalate as you accumulate heat — ICE responds faster and more accurately with each tier.
      </p>

      <div class="ts-rule">// ESCALATION</div>
      <div class="ts-table">
        <div class="ts-trow"><span class="ts-tcell ts-tcell--key">★1 — 10 hacks</span><span class="ts-tcell">ICE starts watching. Rewards ×1.25</span></div>
        <div class="ts-trow"><span class="ts-tcell ts-tcell--key">★2 — 15 hacks</span><span class="ts-tcell">Pings every 2 moves. ×1.50</span></div>
        <div class="ts-trow"><span class="ts-tcell ts-tcell--key">★3 — 20 hacks</span><span class="ts-tcell">Priority ICE target. ×1.75</span></div>
        <div class="ts-trow ts-trow--warn"><span class="ts-tcell ts-tcell--key">★4 — 25 hacks</span><span class="ts-tcell">⚡ OPEN SEASON — all players notified. ×2.00</span></div>
        <div class="ts-trow ts-trow--warn"><span class="ts-tcell ts-tcell--key">★5 — 30 hacks</span><span class="ts-tcell">Max heat. Pings every move. ×2.25</span></div>
      </div>

      <div class="ts-rule">// HOW TO RESET</div>
      <p class="ts-body">
        Visit any <span class="ts-em">CyberDoc</span> and bank your pocket creds.
        Banking clears your bounty entirely — stars back to zero, ICE stands down.
      </p>

    </div>

    <!-- ── POCKET vs WALLET stop ─────────────────────────────────────────── -->
    <div v-else-if="currentStep.id === 'pocket-wallet'" class="ts-content">

      <div class="ts-two-col">
        <div class="ts-economy-box ts-economy-box--pocket">
          <span class="ts-econ-label">POCKET CREDS</span>
          <span class="ts-econ-icon">⚠</span>
          <span class="ts-econ-desc">At risk — earned from hacking</span>
        </div>
        <div class="ts-economy-box ts-economy-box--wallet">
          <span class="ts-econ-label">WALLET CREDS</span>
          <span class="ts-econ-icon">◈</span>
          <span class="ts-econ-desc">Safe — banked at CyberDoc</span>
        </div>
      </div>

      <p class="ts-body">
        Every hack pays out to your <span class="ts-em ts-em--red">pocket</span> — not your wallet.
        Pocket creds are wiped if you're killed in PvP or hit Critical System Failure.
      </p>

      <div class="ts-rule">// HOW TO KEEP IT</div>
      <p class="ts-body">
        Move to any <span class="ts-em">CyberDoc node</span> and hit <span class="ts-em">[ BANK ]</span>.
        Your pocket transfers to your <span class="ts-em">wallet</span> — safe from every threat in the game.
        Banking also resets your bounty to zero.
      </p>

      <div class="ts-rule">// STORES USE WALLET ONLY</div>
      <p class="ts-body">
        CyberDoc upgrades and hardware are purchased from your wallet.
        You can't spend pocket creds directly — bank first, then shop.
      </p>

    </div>

    <!-- ── SS stop ─────────────────────────────────────────────────────────── -->
    <div v-else-if="currentStep.id === 'ss'" class="ts-content">

      <div class="ts-live-row">
        <span class="ts-live-label">SYS.STABILITY</span>
        <span class="ts-live-val" :class="ssClass">
          {{ player?.currentSS ?? '—' }} / {{ player?.maxSS ?? 100 }}
        </span>
      </div>

      <p class="ts-body">
        Your rig's health pool. Failed node breaches always deal a minimum of
        <span class="ts-em">15 damage</span>, scaled by
        <span class="ts-em">nodeICE − yourFirewall</span>.
        High Firewall reduces the hit, but you will always take at least 15.
        In PvP the floor rises to <span class="ts-em">20</span>, scaled by
        <span class="ts-em">your CPU − opponent's Firewall</span>.
      </p>

      <div class="ts-rule">// DEGRADATION</div>
      <p class="ts-body">
        Every 20% of SS lost strips <span class="ts-em">1 point</span> from CPU, RAM, Firewall, and OS.
        As you take damage your rig gets progressively weaker — making each subsequent hit worse.
        Storage is never affected.
      </p>

      <div class="ts-rule">// CRITICAL FAILURE</div>
      <p class="ts-body">
        Hitting 0 triggers <span class="ts-em ts-em--red">Critical System Failure</span> —
        pocket creds wiped, bounty cleared, teleported to the nearest CyberDoc.
        Repair costs up to <span class="ts-em">600₡</span> for a full rebuild.
      </p>

    </div>

    <!-- ── NODE INFO stop ─────────────────────────────────────────────────── -->
    <div v-else-if="currentStep.id === 'node-info'" class="ts-content">

      <p class="ts-body">
        Click any node on the map to inspect it here before you move.
        Use this to plan your route without wasting Uplink.
      </p>

      <div class="ts-rule">// WHAT TO CHECK</div>
      <div class="ts-table">
        <div class="ts-trow">
          <span class="ts-tcell ts-tcell--key">TYPE</span>
          <span class="ts-tcell">Standard, CyberDoc, Spawn, or Safe Zone. Each behaves differently.</span>
        </div>
        <div class="ts-trow">
          <span class="ts-tcell ts-tcell--key">ICE</span>
          <span class="ts-tcell">Breach difficulty. Your CPU must be within <span class="ts-em">4</span> of this value — any higher and the node is locked to you.</span>
        </div>
        <div class="ts-trow">
          <span class="ts-tcell ts-tcell--key">STATUS</span>
          <span class="ts-tcell">Shows if the node is depleted. Depleted nodes replenish after <span class="ts-em">10 minutes</span>.</span>
        </div>
      </div>

      <div class="ts-rule">// CONNECTED PLAYERS</div>
      <p class="ts-body">
        If another runner is on the same node, they appear here.
        You can challenge them to <span class="ts-em">Packet Hijack</span> combat directly from this panel.
      </p>

    </div>
    <!-- ── LOADOUT stop ───────────────────────────────────────────────────── -->
    <div v-else-if="currentStep.id === 'loadout'" class="ts-content">

      <p class="ts-body">
        Commands are programs you install into your rig's loadout slots.
        They modify what you can do on the map and inside breach sequences.
      </p>

      <div class="ts-rule">// USING MAP COMMANDS</div>
      <p class="ts-body">
        Click any equipped command here to select it, then hit
        <span class="ts-em">[ USE ON MAP ]</span> to activate it.
        Effects like <span class="ts-em">Ghost Protocol</span> suppress ICE pings for several moves.
        <span class="ts-em">Signal Noise</span> plants false pings to confuse tracking.
      </p>

      <div class="ts-rule">// HACK COMMANDS</div>
      <p class="ts-body">
        Commands marked <span class="ts-em">HACK</span> activate automatically inside
        Grid-Breach and Packet Hijack — they don't appear in this panel during a breach.
      </p>

      <div class="ts-rule">// COOLDOWNS</div>
      <p class="ts-body">
        Used commands go on cooldown until your next <span class="ts-em">CyberDoc visit</span>.
        Plan your run around how many commands you can afford to burn before banking.
      </p>

    </div>

    <!-- ── TERMINAL stop ──────────────────────────────────────────────────── -->
    <div v-else-if="currentStep.id === 'terminal'" class="ts-content">

      <p class="ts-body">
        The <span class="ts-em">TERMINAL</span> is your mission log.
        Every active objective, NPC contact, and story update appears here.
        If you're ever unsure what to do next — open this first.
      </p>

      <div class="ts-rule">// THE BADGE</div>
      <p class="ts-body">
        The <span class="ts-em ts-em--green">green dot</span> on this button lights up whenever
        a quest step completes. It clears as soon as you open TERMINAL.
      </p>

      <div class="ts-rule">// HOW MISSIONS WORK</div>
      <p class="ts-body">
        Each arc is tied to a specific <span class="ts-em">CyberDoc contact</span>.
        You'll need to visit them in person — move to their node on the map and
        open the dialogue from the side panel to progress the story.
      </p>

      <p class="ts-body">
        Completed missions move to the <span class="ts-em">archive</span>,
        accessible from the TERMINAL page if you want to review past events.
      </p>

    </div>

    <!-- Add more step id blocks here as the tour grows -->

    <!-- ── Footer nav ─────────────────────────────────────────────────────── -->
    <template #footer>
      <div class="ts-footer">
        <button class="ts-skip" @click="skip()">[ skip ]</button>
        <div class="ts-nav">
          <span class="ts-count">{{ stepNumber }} / {{ totalSteps }}</span>
          <button class="ts-next" @click="next()">
            {{ isLast ? '[ DONE ]' : '[ NEXT ]' }}
          </button>
        </div>
      </div>
    </template>

  </FloatingTerminalWindow>
</template>

<script setup>
import { computed } from 'vue';
import FloatingTerminalWindow from '@/components/shared/FloatingTerminalWindow.vue';
import { useUiTour }          from '@/composables/useUiTour.js';

const props = defineProps({
    /** Live player object from useGameState — used to show real stat values */
    player: { type: Object, default: null },
});

const { currentStep, stepNumber, isLast, totalSteps, next, skip } = useUiTour();

const uplinkClass = computed(() => {
    const cur = props.player?.uplink  ?? 0;
    const max = props.player?.maxUplink ?? 1;
    const pct = max > 0 ? cur / max : 1;
    if (pct <= 0)    return 'val--dead';
    if (pct <= 0.33) return 'val--crit';
    if (pct <= 0.66) return 'val--low';
    return 'val--ok';
});

const ssClass = computed(() => {
    const cur = props.player?.currentSS ?? 0;
    const max = props.player?.maxSS     ?? 100;
    const pct = max > 0 ? cur / max : 1;
    if (pct <= 0)    return 'val--dead';
    if (pct <= 0.25) return 'val--crit';
    if (pct <= 0.5)  return 'val--low';
    return 'val--ok';
});
</script>

<style scoped>
/* ── Content wrapper ─────────────────────────────────────────────────────── */
.ts-content {
    padding: 14px 16px 12px;
    display: flex;
    flex-direction: column;
    gap: 0;
}

/* ── Live stat row ────────────────────────────────────────────────────────── */
.ts-live-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    padding: 6px 10px;
    margin-bottom: 12px;
    background: rgba(255, 179, 0, 0.05);
    border: 1px solid rgba(255, 179, 0, 0.18);
}

.ts-live-label {
    font-size: 8px;
    letter-spacing: 0.16em;
    color: rgba(255, 179, 0, 0.45);
}

.ts-live-val {
    font-size: 14px;
    letter-spacing: 0.06em;
    font-weight: 700;
}

.val--ok   { color: #00FF88; text-shadow: 0 0 8px rgba(0,255,136,0.4); }
.val--low  { color: #FFB300; text-shadow: 0 0 8px rgba(255,179,0,0.4); }
.val--crit { color: #FF3333; text-shadow: 0 0 8px rgba(255,51,51,0.5); animation: ts-pulse 0.8s ease-in-out infinite; }
.val--dead { color: rgba(255,51,51,0.3); }

@keyframes ts-pulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.4; }
}

/* ── Section rule ─────────────────────────────────────────────────────────── */
.ts-rule {
    font-size: 8px;
    letter-spacing: 0.16em;
    color: rgba(255, 179, 0, 0.55);
    margin: 10px 0 5px;
}

/* ── Body text ────────────────────────────────────────────────────────────── */
.ts-body {
    margin: 0 0 4px;
    font-size: 11px;
    line-height: 1.65;
    color: rgba(255, 255, 255, 0.88);
}

.ts-em {
    color: #FFB300;
    font-style: normal;
}

.ts-em--red {
    color: #FF3333;
}

.ts-em--green {
    color: #00FF88;
}

/* ── Pocket / Wallet two-col ──────────────────────────────────────────────── */
.ts-two-col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-bottom: 12px;
}

.ts-economy-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 10px 8px;
    border: 1px solid;
    text-align: center;
}

.ts-economy-box--pocket {
    border-color: rgba(255, 51, 51, 0.35);
    background: rgba(255, 51, 51, 0.05);
}

.ts-economy-box--wallet {
    border-color: rgba(0, 255, 136, 0.35);
    background: rgba(0, 255, 136, 0.05);
}

.ts-econ-label {
    font-size: 8px;
    letter-spacing: 0.14em;
    color: rgba(255, 255, 255, 0.65);
}

.ts-econ-icon {
    font-size: 18px;
    line-height: 1;
}

.ts-economy-box--pocket .ts-econ-icon  { color: #FF3333; }
.ts-economy-box--wallet  .ts-econ-icon  { color: #00FF88; }

.ts-econ-desc {
    font-size: 9px;
    color: rgba(255, 255, 255, 0.72);
    line-height: 1.4;
}

/* ── Bounty stars ─────────────────────────────────────────────────────────── */
.ts-stars {
    display: flex;
    gap: 4px;
}

.ts-star {
    font-size: 14px;
    color: rgba(255, 255, 255, 0.15);
    line-height: 1;
}

.ts-star--lit {
    color: #FFB300;
    text-shadow: 0 0 8px rgba(255, 179, 0, 0.8);
}

.ts-star--os {
    color: #FF4444;
    text-shadow: 0 0 8px rgba(255, 68, 68, 0.9);
}

/* ── Info table ───────────────────────────────────────────────────────────── */
.ts-table {
    display: flex;
    flex-direction: column;
    gap: 2px;
    margin-bottom: 4px;
}

.ts-trow {
    display: flex;
    gap: 8px;
    padding: 4px 8px;
    background: rgba(255, 255, 255, 0.03);
    border-left: 2px solid rgba(255, 179, 0, 0.15);
    font-size: 10px;
    line-height: 1.5;
    color: rgba(255, 255, 255, 0.82);
}

.ts-trow--warn {
    border-left-color: rgba(255, 51, 51, 0.45);
    background: rgba(255, 51, 51, 0.04);
    color: rgba(255, 210, 210, 0.88);
}

.ts-tcell {
    flex: 1;
}

.ts-tcell--key {
    flex: 0 0 auto;
    width: 110px;
    color: rgba(255, 179, 0, 0.80);
    letter-spacing: 0.04em;
    font-size: 9px;
}

/* ── Footer ───────────────────────────────────────────────────────────────── */
.ts-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 14px;
}

.ts-skip {
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
.ts-skip:hover { color: rgba(255, 255, 255, 0.55); }

.ts-nav {
    display: flex;
    align-items: center;
    gap: 12px;
}

.ts-count {
    font-size: 9px;
    color: rgba(255, 179, 0, 0.35);
    letter-spacing: 0.1em;
}

.ts-next {
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
.ts-next:hover {
    border-color: rgba(255, 179, 0, 0.9);
    color: #FFB300;
    background: rgba(255, 179, 0, 0.06);
}
</style>
