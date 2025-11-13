<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // pastikan tabel ada
        if (! Schema::hasTable('document_versions')) {
            return;
        }

        Schema::table('document_versions', function (Blueprint $table) {
            // tentukan apakah kolom 'approval_notes' ada -> hanya pakai after() jika ada
            $hasApprovalNotes = Schema::hasColumn('document_versions', 'approval_notes');

            // tambah kolom reject_reason jika belum ada
            if (! Schema::hasColumn('document_versions', 'reject_reason')) {
                if ($hasApprovalNotes) {
                    $table->text('reject_reason')->nullable()->after('approval_notes');
                } else {
                    $table->text('reject_reason')->nullable();
                }
            }

            // tambah kolom rejected_by jika belum ada
            if (! Schema::hasColumn('document_versions', 'rejected_by')) {
                // letakkan setelah reject_reason jika ada, atau setelah approval_notes jika ada, atau otomatis di akhir
                if (Schema::hasColumn('document_versions', 'reject_reason')) {
                    $table->unsignedBigInteger('rejected_by')->nullable()->after('reject_reason');
                } elseif ($hasApprovalNotes) {
                    $table->unsignedBigInteger('rejected_by')->nullable()->after('approval_notes');
                } else {
                    $table->unsignedBigInteger('rejected_by')->nullable();
                }
            }

            // tambah kolom rejected_at jika belum ada
            if (! Schema::hasColumn('document_versions', 'rejected_at')) {
                // place after rejected_by if possible
                if (Schema::hasColumn('document_versions', 'rejected_by')) {
                    $table->timestamp('rejected_at')->nullable()->after('rejected_by');
                } else {
                    $table->timestamp('rejected_at')->nullable();
                }
            }
        });

        // tambahkan FK rejected_by -> users.id jika tabel users ada dan kolom ada
        if (Schema::hasTable('users') && Schema::hasColumn('document_versions', 'rejected_by')) {
            // only add foreign if it does not already exist
            // Some DB drivers may throw if constraint name exists; wrap in try/catch
            try {
                Schema::table('document_versions', function (Blueprint $table) {
                    $table->foreign('rejected_by')->references('id')->on('users')->nullOnDelete();
                });
            } catch (\Throwable $e) {
                // ignore failures to add FK (e.g., constraint already exists)
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('document_versions')) {
            return;
        }

        Schema::table('document_versions', function (Blueprint $table) {
            // drop foreign key first if exists (wrapped in try/catch)
            if (Schema::hasColumn('document_versions', 'rejected_by')) {
                try {
                    $table->dropForeign(['rejected_by']);
                } catch (\Throwable $e) {
                    // ignore if FK does not exist or cannot be dropped
                }
            }

            if (Schema::hasColumn('document_versions', 'reject_reason')) {
                $table->dropColumn('reject_reason');
            }

            if (Schema::hasColumn('document_versions', 'rejected_by')) {
                $table->dropColumn('rejected_by');
            }

            if (Schema::hasColumn('document_versions', 'rejected_at')) {
                $table->dropColumn('rejected_at');
            }
        });
    }
};
