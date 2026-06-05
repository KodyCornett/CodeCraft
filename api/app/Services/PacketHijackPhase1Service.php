<?php

namespace App\Services;

/**
 * PacketHijackPhase1Service — Phase 1 network-recon commands.
 *
 * Owns: netstat, ping, traceroute, arp-scan, whois, sniff.
 * Pure data transforms — no HTTP objects, no model persistence, no constants.
 * State is passed in by the controller and the results returned as plain arrays.
 */
class PacketHijackPhase1Service
{
    /**
     * netstat --active — returns the suspect list as the initial case file.
     * Strips is_target — never exposed to the client.
     */
    public function commandNetstat(array $suspects): array
    {
        return array_map(fn($s) => [
            'ip'      => $s['ip'],
            'flushed' => $s['flushed'],
        ], $suspects);
    }

    /**
     * ping <ip> — probe a specific suspect for latency.
     * Returns latency_ms and latency_status for the matched IP.
     */
    public function commandPing(array $suspects, string $ip): array
    {
        foreach ($suspects as $s) {
            if ($s['ip'] !== $ip) continue;
            return [
                'found'          => true,
                'latency_ms'     => $s['latency_ms'],
                'latency_status' => $s['latency_status'],
            ];
        }

        return ['found' => false, 'error' => "HOST {$ip} NOT FOUND IN TRACE BUFFER"];
    }

    /**
     * traceroute <ip> — reveal hop count and network range for a suspect.
     */
    public function commandTraceroute(array $suspects, string $ip): array
    {
        foreach ($suspects as $s) {
            if ($s['ip'] !== $ip) continue;
            return [
                'found'         => true,
                'hops'          => $s['hops'],
                'network_range' => $s['network_range'],
            ];
        }

        return ['found' => false, 'error' => "ROUTE TO {$ip} UNREACHABLE"];
    }

    /**
     * arp --scan — reveal last-seen timestamps for all suspects at once.
     */
    public function commandArpScan(array $suspects): array
    {
        return array_map(fn($s) => [
            'ip'                => $s['ip'],
            'last_seen_seconds' => $s['last_seen_seconds'],
        ], $suspects);
    }

    /**
     * whois <ip> — reveal chassis class hint for a specific suspect.
     * Returns a redacted response when whois_redacted is true (high OS target).
     */
    public function commandWhois(array $suspects, string $ip): array
    {
        foreach ($suspects as $s) {
            if ($s['ip'] !== $ip) continue;
            return [
                'found'    => true,
                'redacted' => $s['whois_redacted'],
                'class'    => $s['whois_redacted'] ? 'DATA REDACTED' : $s['whois_class'],
            ];
        }

        return ['found' => false, 'error' => "WHOIS RECORD NOT FOUND FOR {$ip}"];
    }

    /**
     * sniff --traffic — intercept one middle octet from the target's live stream.
     * Returns a fragment (e.g. '.4.') as a tiebreaker clue for the player to
     * cross-reference against the case file manually.
     */
    public function commandSniff(string $realIp): string
    {
        $parts = explode('.', $realIp);
        $index = random_int(1, 2);
        return '.' . $parts[$index] . '.';
    }
}
