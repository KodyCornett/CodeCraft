<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Toxic_Soak (cipher-ring wheel) was replaced by Cipher_Lock (cipher-key
 * decrypt puzzle) as the Quest 3 / Float / Spokane Valley minigame. This
 * updates any already-seeded quest_stages rows so the existing DB stays in
 * sync with the new minigame_type string used across the frontend.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('quest_stages')
            ->where('minigame_type', 'toxic_soak')
            ->update(['minigame_type' => 'cipher_lock']);
    }

    public function down(): void
    {
        DB::table('quest_stages')
            ->where('minigame_type', 'cipher_lock')
            ->update(['minigame_type' => 'toxic_soak']);
    }
};
