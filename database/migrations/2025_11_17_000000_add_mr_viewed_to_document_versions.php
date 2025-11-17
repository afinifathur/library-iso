<?php
// database/migrations/2025_11_17_000000_add_mr_viewed_to_document_versions.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (Schema::hasTable('document_versions') && ! Schema::hasColumn('document_versions','mr_viewed_at')) {
            Schema::table('document_versions', function (Blueprint $table) {
                $table->timestamp('mr_viewed_at')->nullable()->after('submitted_at')->comment('Timestamp when MR opened version for review');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('document_versions') && Schema::hasColumn('document_versions','mr_viewed_at')) {
            Schema::table('document_versions', function (Blueprint $table) {
                $table->dropColumn('mr_viewed_at');
            });
        }
    }
};
