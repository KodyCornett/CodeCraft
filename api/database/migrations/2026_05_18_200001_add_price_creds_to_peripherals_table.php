<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('peripherals', function (Blueprint $table) {
            $table->unsignedInteger('price_creds')->default(0)->after('port_cost');
        });
    }

    public function down(): void
    {
        Schema::table('peripherals', function (Blueprint $table) {
            $table->dropColumn('price_creds');
        });
    }
};
