<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Correct SS repair kit prices to match the design doc:
     *   25 SS  → 150 ₡
     *   50 SS  → 300 ₡
     *  100 SS  → 600 ₡
     */
    public function up(): void
    {
        DB::table('consumables')
            ->where('name', 'SS Stabilizer (25)')
            ->update(['price_creds' => 150]);

        DB::table('consumables')
            ->where('name', 'SS Stabilizer (50)')
            ->update(['price_creds' => 300]);

        DB::table('consumables')
            ->where('name', 'Full System Restore')
            ->update(['price_creds' => 600]);
    }

    public function down(): void
    {
        DB::table('consumables')
            ->where('name', 'SS Stabilizer (25)')
            ->update(['price_creds' => 1200]);

        DB::table('consumables')
            ->where('name', 'SS Stabilizer (50)')
            ->update(['price_creds' => 2000]);

        DB::table('consumables')
            ->where('name', 'Full System Restore')
            ->update(['price_creds' => 4500]);
    }
};
