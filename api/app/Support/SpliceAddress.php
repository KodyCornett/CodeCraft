<?php

namespace App\Support;

/**
 * Generates a node's SPLICE address (format ZONE.HASH, e.g. 14.A3F9).
 *
 * This is a bit-for-bit PHP port of the djb2 hash + zone-code logic in
 * resources/js/composables/useNodeIdentity.js. It has to match exactly —
 * verified against the JS implementation on 500+ random UUIDs before being
 * wired into the backfill migration — because existing nodes already have
 * addresses players may have already seen or tracked in Splice Maps, and
 * drifting from the original formula would silently change them out from
 * under anyone who's already found one.
 *
 * Only consulted to populate Node::splice_address — once, at creation (see
 * Node::booted()) or in the one-time backfill migration. After that the
 * stored column is always authoritative; this class is never re-derived
 * from at read time. That's deliberate — it's what lets a writer hand-set
 * a specific node's address instead of always taking whatever the hash
 * produces.
 */
class SpliceAddress
{
    private const DISTRICT_ZONE = [
        'North Spokane'       => 14,
        'Spokane Valley'      => 21,
        'Downtown'            => 35,
        "Browne's Addition"   => 49,
        'University District' => 63,
    ];

    public static function generate(string $nodeId, ?string $district): string
    {
        $hash = self::djb2($nodeId . '_s');
        $zone = self::zoneCode($district);
        $hex  = strtoupper(str_pad(dechex($hash & 0xFFFF), 4, '0', STR_PAD_LEFT));

        return "{$zone}.{$hex}";
    }

    /**
     * Same algorithm as useNodeIdentity.js's djb2(), with explicit 32-bit
     * signed wraparound at each step to match JavaScript's implicit
     * ToInt32 behavior on bitwise operators — PHP ints are 64-bit and
     * don't wrap the same way on their own, so each intermediate result
     * has to be masked back down by hand.
     */
    private static function djb2(string $str): int
    {
        $hash = 5381;
        $len  = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            $shifted = self::toInt32($hash << 5);
            $sum     = $shifted + $hash;
            $hash    = self::toInt32($sum ^ ord($str[$i]));
        }
        return abs($hash);
    }

    private static function toInt32(int $n): int
    {
        $n = $n & 0xFFFFFFFF;
        if ($n >= 0x80000000) {
            $n -= 0x100000000;
        }
        return $n;
    }

    private static function zoneCode(?string $district): string
    {
        if ($district !== null && array_key_exists($district, self::DISTRICT_ZONE)) {
            return str_pad((string) self::DISTRICT_ZONE[$district], 2, '0', STR_PAD_LEFT);
        }
        if ($district !== null && preg_match('/[A-Za-z]+(\d+)/', $district, $m)) {
            return str_pad((string) (int) $m[1], 2, '0', STR_PAD_LEFT);
        }
        return '00';
    }
}
