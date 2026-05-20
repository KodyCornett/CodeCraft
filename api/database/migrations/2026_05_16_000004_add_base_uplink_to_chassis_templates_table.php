<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chassis_templates', function (Blueprint $table) {
            // Uplink is chassis-locked — cannot be boosted by peripherals.
            // BlackHat v1.0 ships with 3 uplink (movement points per session).
            $table->unsignedSmallInteger('base_uplink')->default(3)->after('base_storage');
        });

        // Seed existing BlackHat v1.0 row
        DB::table('chassis_templates')
            ->where('name', 'BlackHat v1.0')
            ->update(['base_uplink' => 3]);
    }

    public function down(): void
    {
        Schema::table('chassis_templates', function (Blueprint $table) {
            $table->dropColumn('base_uplink');
        });
    }
};
