<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add is_safe_zone to nodes.
     * CyberDoc nodes are safe zones — PvP is not permitted on them.
     * All other nodes default to false.
     */
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->boolean('is_safe_zone')->default(false)->after('is_spawn');
        });

        DB::table('nodes')
            ->where('type', 'cyberdoc')
            ->update(['is_safe_zone' => true]);
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn('is_safe_zone');
        });
    }
};
