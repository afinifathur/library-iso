<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSubmissionFieldsToDocumentVersions extends Migration
{
    public function up()
    {
        Schema::table('document_versions', function (Blueprint $table) {
            if (! Schema::hasColumn('document_versions', 'submitted_by')) {
                $table->unsignedBigInteger('submitted_by')->nullable()->after('created_by');
            }
            if (! Schema::hasColumn('document_versions', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            }
        });
    }

    public function down()
    {
        Schema::table('document_versions', function (Blueprint $table) {
            if (Schema::hasColumn('document_versions', 'submitted_at')) {
                $table->dropColumn('submitted_at');
            }
            if (Schema::hasColumn('document_versions', 'submitted_by')) {
                $table->dropColumn('submitted_by');
            }
        });
    }
}
