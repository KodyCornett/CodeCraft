<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packet_hijack_matches', function (Blueprint $table) {
            // Practice matches have no real opponent, so defender columns must be nullable.
            $table->foreignUuid('defender_id')->nullable()->change();
            $table->string('defender_target_ip', 20)->nullable()->change();
            $table->json('defender_ports')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('packet_hijack_matches', function (Blueprint $table) {
            $table->foreignUuid('defender_id')->nullable(false)->change();
            $table->string('defender_target_ip', 20)->nullable(false)->change();
            $table->json('defender_ports')->nullable(false)->change();
        });
    }
};
