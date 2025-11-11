<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable()->after('email');
                // buat foreign key jika tabel departments sudah ada
                if (Schema::hasTable('departments')) {
                    $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'department_id')) {
                // drop foreign key if exists (name may vary)
                try {
                    $table->dropForeign(['department_id']);
                } catch (\Throwable $e) {}
                $table->dropColumn('department_id');
            }
        });
    }
};
