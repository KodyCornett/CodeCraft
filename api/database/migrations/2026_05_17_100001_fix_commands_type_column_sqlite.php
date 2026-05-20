<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The original commands table defined `type` as an enum with only
 * ('offensive', 'defensive', 'movement'). The expand_commands migration
 * correctly skipped the ALTER COLUMN on SQLite (unsupported), leaving the
 * CHECK constraint in place.
 *
 * This migration replaces the `type` column with a plain string so that
 * all five types — offensive, defensive, movement, trap, stealth — are
 * accepted. Validation is enforced at the application layer.
 *
 * Approach: copy rows to a temp table, drop + recreate, copy back.
 * SQLite does not support ALTER COLUMN; a full rebuild is the only option.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->rebuildCommandsTypeSqlite();
        } else {
            // MySQL / Postgres — straightforward change()
            Schema::table('commands', function (Blueprint $table) {
                $table->enum('type', ['offensive', 'defensive', 'movement', 'trap', 'stealth'])
                    ->default('offensive')
                    ->change();
            });
        }
    }

    public function down(): void
    {
        // Reverting the constraint is destructive on SQLite — left as no-op.
        // If you need to roll back, wipe the DB and re-migrate from scratch.
    }

    // -------------------------------------------------------------------------

    private function rebuildCommandsTypeSqlite(): void
    {
        // 1. Snapshot all existing rows
        $rows = DB::table('commands')->get();

        // 2. Drop the table entirely (removes the stale CHECK constraint)
        Schema::drop('commands');

        // 3. Recreate with `type` as a plain string
        Schema::create('commands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->unsignedTinyInteger('tier')->default(1);
            $table->string('type', 20)->default('offensive');   // no CHECK constraint
            $table->text('description')->nullable();
            $table->unsignedInteger('price_creds')->default(0);
            $table->unsignedInteger('price_tp')->default(0);
            $table->string('target_type', 20)->default('self');
            $table->text('map_effect')->nullable();
            $table->text('hack_effect')->nullable();
            $table->json('duration')->nullable();
            $table->timestamps();
        });

        // 4. Restore the original rows (if any existed before the seeder ran)
        foreach ($rows as $row) {
            DB::table('commands')->insert((array) $row);
        }
    }
};
