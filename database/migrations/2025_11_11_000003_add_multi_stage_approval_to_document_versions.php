<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('document_versions', function (Blueprint $table) {
            if (!Schema::hasColumn('document_versions', 'approval_stage')) {
                $table->string('approval_stage', 30)->default('KABAG')->after('status');
            }
        });

        if (!Schema::hasTable('approval_logs')) {
            Schema::create('approval_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_version_id')->constrained('document_versions')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('role', 50)->nullable();
                $table->enum('action', ['submit','approve','reject'])->default('submit');
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('approval_logs')) Schema::dropIfExists('approval_logs');
        Schema::table('document_versions', function (Blueprint $table) {
            if (Schema::hasColumn('document_versions','approval_stage')) {
                $table->dropColumn('approval_stage');
            }
        });
    }
};
