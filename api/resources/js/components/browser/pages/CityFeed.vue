<template>
    <div class="feed-page">

        <header class="feed-header">
            <span class="feed-title">◉ DARKNET // SPOKANE FEED</span>
            <span class="feed-clock">LIVE // {{ time }}</span>
        </header>

        <div class="feed-ticker">
            ▶ BREAKING: NODE CLUSTER COMPROMISED IN HILLYARD SECTOR — GHOST RUNNERS SUSPECTED
            &nbsp;&nbsp;&nbsp;▶&nbsp;&nbsp;&nbsp;
            UPLINK PRICES SURGE 12% AFTER VALLEY RELAY TAKEN OFFLINE
            &nbsp;&nbsp;&nbsp;▶&nbsp;&nbsp;&nbsp;
            OPEN BOUNTY ACTIVE: HANDLE "PHANTOM_ZERO" — 4,500 CREDS
        </div>

        <div class="feed-list">
            <article v-for="item in feedItems" :key="item.id" class="feed-item">
                <div class="fi-meta">
                    <span class="fi-tag" :class="`fi-tag--${item.tag}`">{{ item.tag.toUpperCase() }}</span>
                    <span class="fi-time">{{ item.time }}</span>
                    <span class="fi-sector">// {{ item.sector }}</span>
                </div>
                <h3 class="fi-headline">{{ item.headline }}</h3>
                <p  class="fi-body">{{ item.body }}</p>
            </article>
        </div>

    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

defineProps({ url: { type: String, default: '' } });

const time = ref('');
let timer;
onMounted(() => {
    const tick = () => { time.value = new Date().toLocaleTimeString('en-US', { hour12: false }); };
    tick();
    timer = setInterval(tick, 1000);
});
onUnmounted(() => clearInterval(timer));

const feedItems = [
    {
        id: 1,
        tag: 'breach',
        time: '02:14:33',
        sector: 'NORTH SPOKANE',
        headline: 'Shadle Park relay cluster hit by coordinated UPLINK drain',
        body: 'Three nodes in the SHADLE_MESH subnet went dark overnight. Witnesses report a ghost runner operating under the handle "NULLWAVE" before disappearing into the Wandermere buffer zone. No arrests.',
    },
    {
        id: 2,
        tag: 'market',
        time: '01:58:12',
        sector: 'DOWNTOWN',
        headline: 'CyberDoc vendor network expands to Fox Theatre node',
        body: 'The underground hardware collective known as CyberDoc has opened a new distribution point near FOX_THEATRE_NODE. Stock includes Firewall Patch Mk.II units and RAM Overdrive modules. Prices trending below average.',
    },
    {
        id: 3,
        tag: 'bounty',
        time: '01:22:09',
        sector: 'SPOKANE VALLEY',
        headline: 'Open season declared on runner PHANTOM_ZERO',
        body: 'A 4,500 CRED bounty has been placed on PHANTOM_ZERO following last week\'s data heist at the Sullivan relay. Runner was last traced to the MIRABEAU subnet. Bounty posted by anonymous corporate entity.',
    },
    {
        id: 4,
        tag: 'alert',
        time: '00:47:55',
        sector: "BROWNE'S ADDITION",
        headline: 'PACIFIC_PARK node flagged for active trace sweep',
        body: 'Runners active in Browne\'s Addition are advised to route around PACIFIC_PARK_HUB. Corporate security sweep in progress. Estimated clearance: 06:00. GRAND_AVE node remains clean.',
    },
    {
        id: 5,
        tag: 'intel',
        time: '00:11:40',
        sector: 'UNIVERSITY DISTRICT',
        headline: 'Gonzaga subnet TECH cache refreshed — high value window open',
        body: 'Sources indicate the GONZAGA_NODE cluster replenished its TECH point reserves following last night\'s sweep. Short window before corporate harvesters move in. HAMILTON and BOONE nodes also reporting surplus.',
    },
];
</script>

<style scoped>
.feed-page {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #06060d;
    font-family: 'JetBrains Mono', monospace;
    overflow: hidden;
}

.feed-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 20px 12px;
    border-bottom: 1px solid rgba(0, 255, 255, 0.1);
    flex-shrink: 0;
}

.feed-title {
    font-size: 13px;
    color: #00FFFF;
    letter-spacing: 0.1em;
    text-shadow: 0 0 10px rgba(0, 255, 255, 0.35);
}

.feed-clock {
    font-size: 9px;
    color: rgba(0, 255, 255, 0.3);
    letter-spacing: 0.1em;
}

.feed-ticker {
    padding: 7px 20px;
    background: rgba(0, 255, 136, 0.03);
    border-bottom: 1px solid rgba(0, 255, 136, 0.08);
    font-size: 8px;
    color: rgba(0, 255, 136, 0.55);
    letter-spacing: 0.07em;
    white-space: nowrap;
    overflow: hidden;
    flex-shrink: 0;
}

.feed-list {
    flex: 1;
    overflow-y: auto;
    padding: 16px 20px;
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.feed-item {
    border: 1px solid rgba(0, 255, 255, 0.07);
    padding: 14px 16px;
    background: rgba(0, 255, 255, 0.015);
    display: flex;
    flex-direction: column;
    gap: 7px;
    transition: border-color 0.15s;
}
.feed-item:hover {
    border-color: rgba(0, 255, 255, 0.15);
}

.fi-meta {
    display: flex;
    align-items: center;
    gap: 10px;
}

.fi-tag {
    font-size: 7px;
    letter-spacing: 0.12em;
    padding: 2px 7px;
    border: 1px solid;
}
.fi-tag--breach { color: #FF3333; border-color: rgba(255, 51,  51,  0.4); background: rgba(255, 51,  51,  0.06); }
.fi-tag--market { color: #FFB300; border-color: rgba(255, 179, 0,   0.4); background: rgba(255, 179, 0,   0.06); }
.fi-tag--bounty { color: #FF69B4; border-color: rgba(255, 105, 180, 0.4); background: rgba(255, 105, 180, 0.06); }
.fi-tag--alert  { color: #7DF9FF; border-color: rgba(125, 249, 255, 0.4); background: rgba(125, 249, 255, 0.06); }
.fi-tag--intel  { color: #00FF88; border-color: rgba(0,   255, 136, 0.4); background: rgba(0,   255, 136, 0.06); }

.fi-time, .fi-sector {
    font-size: 8px;
    color: rgba(0, 255, 255, 0.28);
    letter-spacing: 0.08em;
}

.fi-headline {
    font-size: 11px;
    font-weight: normal;
    color: rgba(255, 255, 255, 0.82);
    letter-spacing: 0.05em;
    line-height: 1.4;
    margin: 0;
}

.fi-body {
    font-size: 9px;
    color: rgba(255, 255, 255, 0.4);
    letter-spacing: 0.04em;
    line-height: 1.75;
    margin: 0;
}
</style>
