/**
 * scenarios/index — registry of SIT scenarios, keyed by a short string
 * both SIT.vue (to build the active session) and DevSITLab.vue (to offer
 * a launch button per scenario) can use without either one needing to
 * import individual scenario files directly.
 */

import { buildVpnCredentialsScenario } from './vpnCredentials.js';
import { buildRelayIpScenario } from './relayIp.js';

export const SCENARIOS = {
    vpn_credentials: {
        label: 'VPN CREDENTIALS',
        summary: 'Read mail + logs, combine a two-part password formula, login.',
        build: buildVpnCredentialsScenario,
    },
    relay_ip: {
        label: 'RELAY IP TRACE',
        summary: 'Reconstruct a full IP address across three documents, connect.',
        build: buildRelayIpScenario,
    },
};

export const DEFAULT_SCENARIO_KEY = 'vpn_credentials';

export function buildScenario(key) {
    const entry = SCENARIOS[key] ?? SCENARIOS[DEFAULT_SCENARIO_KEY];
    return entry.build();
}
