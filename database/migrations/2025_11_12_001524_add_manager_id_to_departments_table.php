<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan migrasi.
     *
     * Menambahkan kolom manager_id ke tabel departments
     * dan membuat foreign key ke tabel users.
     */
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            // Tambahkan kolom manager_id setelah kolom name
            $table->unsignedBigInteger('manager_id')->nullable()->after('name');

            // Definisikan relasi ke tabel users (jika user dihapus → set null)
            $table->foreign('manager_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    /**
     * Rollback migrasi.
     *
     * Menghapus kolom dan foreign key manager_id dari tabel departments.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            // Hapus foreign key terlebih dahulu
            $table->dropForeign(['manager_id']);

            // Lalu hapus kolom manager_id
            $table->dropColumn('manager_id');
        });
    }
};
