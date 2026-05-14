/**
 * PingMarker — D3 rendering utilities for footprint ping markers.
 *
 * Pings render as rotated squares (diamonds) on a dedicated <g> layer.
 * Color and size vary with age and loudness.
 *
 * Usage: import { renderPings } from './PingMarker.js'
 * Then call: renderPings(d3, pingsGroup, pings, nodePositions)
 */

const DIAMOND_NORMAL = 5;
const DIAMOND_LOUD   = 9;

function diamondPath(x, y, size) {
    return `M${x},${y - size} L${x + size},${y} L${x},${y + size} L${x - size},${y} Z`;
}

function pingStyle(ping) {
    const ageMins = (Date.now() - ping.createdAt) / 60000;
    if (ping.isLoud) return { fill: '#FFB300', opacity: 0.90 };
    if (ageMins < 1) return { fill: '#FF00CC', opacity: 0.85 };
    if (ageMins < 3) return { fill: '#CC0099', opacity: 0.50 };
    return               { fill: '#555555',  opacity: 0.25 };
}

/**
 * @param {object}  d3            - The d3 module (imported by caller)
 * @param {object}  group         - d3 selection of the <g> layer for pings
 * @param {Array}   pings         - [ { pingId, nodeId, createdAt, expiresAt, isLoud } ]
 * @param {Map}     nodePositions - Map<nodeId, { x, y }>
 */
export function renderPings(d3, group, pings, nodePositions) {
    const now    = Date.now();
    const active = pings.filter(p => now < p.expiresAt);

    group
        .selectAll('path.ping-marker')
        .data(active, d => d.pingId)
        .join(
            enter => enter
                .append('path')
                .attr('class', p => `ping-marker${p.isLoud ? ' ping-loud' : ''}`),
        )
        .attr('d', p => {
            const pos = nodePositions.get(p.nodeId);
            if (!pos) return '';
            const sz = p.isLoud ? DIAMOND_LOUD : DIAMOND_NORMAL;
            return diamondPath(pos.x + sz * 0.4, pos.y - sz * 0.4, sz);
        })
        .attr('fill',    p => pingStyle(p).fill)
        .attr('opacity', p => pingStyle(p).opacity);
}
