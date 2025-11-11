<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('document_versions', function (Blueprint $table) {
            // ubah kolom file_path menjadi nullable
            if (Schema::hasColumn('document_versions', 'file_path')) {
                $table->string('file_path')->nullable()->change();
            }
            // juga pastikan file_mime nullable (opsional)
            if (Schema::hasColumn('document_versions', 'file_mime')) {
                $table->string('file_mime')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('document_versions', function (Blueprint $table) {
            if (Schema::hasColumn('document_versions','file_path')) {
                $table->string('file_path')->nullable(false)->change();
            }
            if (Schema::hasColumn('document_versions','file_mime')) {
                $table->string('file_mime')->nullable(false)->change();
            }
        });
    }
};
