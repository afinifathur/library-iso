<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddApprovalFieldsToDocumentVersions extends Migration
{
    public function up()
    {
        Schema::table('document_versions', function (Blueprint $table) {
            if (! Schema::hasColumn('document_versions', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('signed_at');
            }
            if (! Schema::hasColumn('document_versions', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('document_versions', 'approval_note')) {
                $table->text('approval_note')->nullable()->after('approved_at');
            }
        });
    }

    public function down()
    {
        Schema::table('document_versions', function (Blueprint $table) {
            if (Schema::hasColumn('document_versions', 'approval_note')) {
                $table->dropColumn('approval_note');
            }
            if (Schema::hasColumn('document_versions', 'approved_at')) {
                $table->dropColumn('approved_at');
            }
            if (Schema::hasColumn('document_versions', 'approved_by')) {
                $table->dropColumn('approved_by');
            }
        });
    }
}
