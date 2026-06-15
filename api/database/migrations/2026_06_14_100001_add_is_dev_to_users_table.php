<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an is_dev flag to the users table.
 *
 * Dev accounts can see the hex map and other in-development features.
 * Set to true via tinker for test accounts:
 *
 *   php artisan tinker
 *   \App\Models\User::where('email', 'your@email.com')->update(['is_dev' => true]);
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_dev')->default(false)->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_dev');
        });
    }
};
