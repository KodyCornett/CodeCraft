<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestStage extends Model
{
    use HasUuids;

    protected $fillable = [
        'quest_arc_id',
        'stage_number',
        'title',
        'objective_text',
        'rep_reward',
        'is_branch',
        'branch_options',
        'referral_doc_id',
        'referral_text',
        // Rewards
        'reward_creds',
        'reward_tech_points',
        'reward_item_id',
        'reward_command_id',
        'reward_node_access',
        'reward_lore_key',
        // Triggers
        'node_canvas_id',
        'minigame_type',
        'dialogue',
    ];

    protected $casts = [
        'stage_number'       => 'integer',
        'rep_reward'         => 'integer',
        'is_branch'          => 'boolean',
        'branch_options'     => 'array',
        'dialogue'           => 'array',
        'reward_creds'       => 'integer',
        'reward_tech_points' => 'decimal:2',
    ];

    public function arc(): BelongsTo
    {
        return $this->belongsTo(QuestArc::class, 'quest_arc_id');
    }

    public function referralDoc(): BelongsTo
    {
        return $this->belongsTo(CyberDoc::class, 'referral_doc_id');
    }
}
