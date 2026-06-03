<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->enum('zone_type', ['district', 'neighborhood', 'netlink'])->default('netlink')->after('district');
            $table->string('zone_group', 64)->nullable()->index()->after('zone_type');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn(['zone_type', 'zone_group']);
        });
    }
};
