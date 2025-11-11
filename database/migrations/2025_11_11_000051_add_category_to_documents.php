<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasColumn('documents', 'category')) {
                $table->string('category', 20)->nullable()->after('doc_code')->comment('IK, UT, FR, PJM, MJM, DP, DE');
            }
            if (! Schema::hasColumn('documents', 'doc_number')) {
                $table->string('doc_number', 20)->nullable()->after('category')->comment('nomor urut seperti 001');
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents','doc_number')) $table->dropColumn('doc_number');
            if (Schema::hasColumn('documents','category')) $table->dropColumn('category');
        });
    }
};
