<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            // Change from integer to decimal(8,2) so fractional TP (e.g. 1.25, 0.5)
            // can be stored and returned accurately. Existing integer values are
            // losslessly preserved (1 → 1.00). SQLite stores these as REAL natively.
            $table->decimal('tech_points', 8, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->integer('tech_points')->default(0)->change();
        });
    }
};
