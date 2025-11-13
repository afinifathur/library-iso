<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom "code" ke tabel categories
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (!Schema::hasColumn('categories', 'code')) {
                $table->string('code', 20)
                    ->after('id')
                    ->unique()
                    ->index()
                    ->comment('Kode kategori seperti IK, UT, FR, DP, DE');
            }
        });
    }

    /**
     * Hapus kolom "code" jika rollback
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'code')) {
                $table->dropColumn('code');
            }
        });
    }
};
