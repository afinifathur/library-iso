<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddApprovalFieldsToDocumentVersions extends Migration
{
    public function up()
    {
        // document_versions
        if (Schema::hasTable('document_versions')) {
            Schema::table('document_versions', function (Blueprint $table) {
                // approval_stage: gunakan integer jika workflow numeric; ubah ke string jika kamu memang butuh label
                if (! Schema::hasColumn('document_versions', 'approval_stage')) {
                    $table->integer('approval_stage')->nullable()->after('status');
                }

                if (! Schema::hasColumn('document_versions', 'submitted_by')) {
                    $table->unsignedBigInteger('submitted_by')->nullable()->after('approval_stage');
                }
                if (! Schema::hasColumn('document_versions', 'submitted_at')) {
                    $table->timestamp('submitted_at')->nullable()->after('submitted_by');
                }

                if (! Schema::hasColumn('document_versions', 'rejected_by')) {
                    $table->unsignedBigInteger('rejected_by')->nullable()->after('submitted_at');
                }
                if (! Schema::hasColumn('document_versions', 'rejected_at')) {
                    $table->timestamp('rejected_at')->nullable()->after('rejected_by');
                }

                // create 'rejected_reason' if missing, and we'll copy from 'reject_reason' if present
                if (! Schema::hasColumn('document_versions', 'rejected_reason')) {
                    $table->text('rejected_reason')->nullable()->after('rejected_at');
                }

                if (! Schema::hasColumn('document_versions', 'approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable()->after('rejected_reason');
                }
                if (! Schema::hasColumn('document_versions', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->after('approved_by');
                }
            });

            // Jika tabel punya kolom lama 'reject_reason', salin nilainya ke 'rejected_reason' (sekali saja)
            if (Schema::hasColumn('document_versions', 'reject_reason') && Schema::hasColumn('document_versions', 'rejected_reason')) {
                // hanya update baris yang belum punya rejected_reason
                DB::table('document_versions')
                    ->whereNull('rejected_reason')
                    ->whereNotNull('reject_reason')
                    ->update(['rejected_reason' => DB::raw('reject_reason')]);
            }
        }

        // documents
        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table) {
                if (! Schema::hasColumn('documents', 'current_version_id')) {
                    $table->unsignedBigInteger('current_version_id')->nullable()->after('department_id');
                }

                // gunakan string untuk menjaga format '001' jika perlu
                if (! Schema::hasColumn('documents', 'revision_number')) {
                    $table->string('revision_number')->nullable()->after('current_version_id');
                }

                if (! Schema::hasColumn('documents', 'revision_date')) {
                    $table->date('revision_date')->nullable()->after('revision_number');
                }

                // opsional: jika kamu ingin menyimpan approved_by / approved_at di parent document
                if (! Schema::hasColumn('documents', 'approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable()->after('revision_date');
                }
                if (! Schema::hasColumn('documents', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->after('approved_by');
                }

                // opsional: jika kamu ingin menyimpan rejected_reason ringkasan di parent
                if (! Schema::hasColumn('documents', 'rejected_reason')) {
                    $table->text('rejected_reason')->nullable()->after('description');
                }
            });
        }
    }

    public function down()
    {
        // document_versions drops (cek existensi sebelum drop)
        if (Schema::hasTable('document_versions')) {
            Schema::table('document_versions', function (Blueprint $table) {
                if (Schema::hasColumn('document_versions', 'approved_at')) {
                    $table->dropColumn('approved_at');
                }
                if (Schema::hasColumn('document_versions', 'approved_by')) {
                    $table->dropColumn('approved_by');
                }
                if (Schema::hasColumn('document_versions', 'rejected_reason')) {
                    $table->dropColumn('rejected_reason');
                }
                if (Schema::hasColumn('document_versions', 'rejected_at')) {
                    $table->dropColumn('rejected_at');
                }
                if (Schema::hasColumn('document_versions', 'rejected_by')) {
                    $table->dropColumn('rejected_by');
                }
                if (Schema::hasColumn('document_versions', 'submitted_at')) {
                    $table->dropColumn('submitted_at');
                }
                if (Schema::hasColumn('document_versions', 'submitted_by')) {
                    $table->dropColumn('submitted_by');
                }
                if (Schema::hasColumn('document_versions', 'approval_stage')) {
                    $table->dropColumn('approval_stage');
                }
            });
        }

        // documents drops (cek existensi sebelum drop)
        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table) {
                if (Schema::hasColumn('documents', 'rejected_reason')) {
                    $table->dropColumn('rejected_reason');
                }
                if (Schema::hasColumn('documents', 'approved_at')) {
                    $table->dropColumn('approved_at');
                }
                if (Schema::hasColumn('documents', 'approved_by')) {
                    $table->dropColumn('approved_by');
                }
                if (Schema::hasColumn('documents', 'revision_date')) {
                    $table->dropColumn('revision_date');
                }
                if (Schema::hasColumn('documents', 'revision_number')) {
                    $table->dropColumn('revision_number');
                }
                // current_version_id cukup hati-hati: jika environment lain juga menambahkan, hapus bila memang milik migration ini
                if (Schema::hasColumn('documents', 'current_version_id')) {
                    $table->dropColumn('current_version_id');
                }
            });
        }
    }
}
