<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename the primary table
        Schema::rename('street_docs', 'cyber_docs');

        // Update the users table flags
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('is_streetdoc', 'is_cyberdoc');
        });

        // Update the foreign keys and columns in related tables
        Schema::table('medical_procedures', function (Blueprint $table) {
            $table->renameColumn('street_doc_notes', 'cyber_doc_notes');
        });
    }

    public function down(): void
    {
        Schema::table('medical_procedures', function (Blueprint $table) {
            $table->renameColumn('cyber_doc_notes', 'street_doc_notes');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('is_cyberdoc', 'is_streetdoc');
        });

        Schema::rename('cyber_docs', 'street_docs');
    }
};