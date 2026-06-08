<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('street_docs', 'cyber_docs');
        Schema::rename('street_doc_catalog', 'cyber_doc_catalog');

        Schema::table('cyber_doc_catalog', function (Blueprint $table) {
            $table->renameColumn('street_doc_id', 'cyber_doc_id');
        });

        Schema::table('players', function (Blueprint $table) {
            $table->renameColumn('last_street_doc_id', 'last_cyber_doc_id');
        });
    }

    public function down(): void
    {
        Schema::rename('cyber_docs', 'street_docs');
        Schema::rename('cyber_doc_catalog', 'street_doc_catalog');

        Schema::table('street_doc_catalog', function (Blueprint $table) {
            $table->renameColumn('cyber_doc_id', 'street_doc_id');
        });

        Schema::table('players', function (Blueprint $table) {
            $table->renameColumn('last_cyber_doc_id', 'last_street_doc_id');
        });
    }
};
