<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRelatedLinksToDocuments extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('documents')) {
            return;
        }

        if (! Schema::hasColumn('documents', 'related_links')) {
            Schema::table('documents', function (Blueprint $table) {
                // kalau kolom current_version_id ada → taruh setelahnya
                if (Schema::hasColumn('documents', 'current_version_id')) {
                    $table->text('related_links')->nullable()->after('current_version_id');
                } 
                // kalau tidak ada, langsung append ke akhir tabel (lebih aman)
                else {
                    $table->text('related_links')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('documents') && Schema::hasColumn('documents', 'related_links')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropColumn('related_links');
            });
        }
    }
}
