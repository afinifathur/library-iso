<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            if (!Schema::hasColumn('departments','code')) {
                $table->string('code', 50)->nullable()->after('id');
            }
            if (!Schema::hasColumn('departments','pic_name')) {
                $table->string('pic_name', 100)->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            if (Schema::hasColumn('departments','pic_name')) {
                $table->dropColumn('pic_name');
            }
            if (Schema::hasColumn('departments','code')) {
                $table->dropColumn('code');
            }
        });
    }
};
