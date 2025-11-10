<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('document_versions', function (Blueprint $table) {
            $table->unsignedBigInteger('prev_version_id')->nullable()->after('id');
            $table->index('prev_version_id', 'dv_prev_version_idx');
            // Jika ingin FK dan tabelnya besar, hati-hati overhead:
            // $table->foreign('prev_version_id')->references('id')->on('document_versions')->nullOnDelete();
        });
    }
    public function down(): void
    {
        Schema::table('document_versions', function (Blueprint $table) {
            // $table->dropForeign(['prev_version_id']);
            $table->dropIndex('dv_prev_version_idx');
            $table->dropColumn('prev_version_id');
        });
    }
};
