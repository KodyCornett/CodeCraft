<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Districts were stored as a lookup table but are never queried —
 * nodes carry their district name as a plain string column.
 * The District model and this table are vestigial from the old design.
 *
 * Rollback recreates the table so migrate:rollback is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('districts');
    }

    public function down(): void
    {
        Schema::create('districts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });
    }
};
