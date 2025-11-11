<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasColumn('documents','current_version_id')) {
                $table->unsignedBigInteger('current_version_id')->nullable()->after('department_id')->comment('active published version');
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents','current_version_id')) {
                $table->dropColumn('current_version_id');
            }
        });
    }
};
