<?php

namespace App\Services;

/**
 * Value object returned from BountyService state-change methods.
 *
 * type values:
 *   'none'                  — threshold not crossed, nothing special happened
 *   'bounty_marked'         — player just crossed onto the bounty board
 *   'open_season_triggered' — player just entered Open Season status
 */
class BountyEvent
{
    public function __construct(
        public readonly string $type,
        public readonly array  $data = [],
    ) {}

    public static function none(): self
    {
        return new self('none');
    }

    public static function bountyMarked(int $bountyLevel): self
    {
        return new self('bounty_marked', ['bounty_level' => $bountyLevel]);
    }

    public static function openSeasonTriggered(int $bountyLevel): self
    {
        return new self('open_season_triggered', ['bounty_level' => $bountyLevel]);
    }
}
