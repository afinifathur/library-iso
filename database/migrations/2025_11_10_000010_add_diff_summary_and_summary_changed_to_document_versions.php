<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDiffSummaryAndSummaryChangedToDocumentVersions extends Migration
{
    public function up()
    {
        Schema::table('document_versions', function (Blueprint $table) {
            if (! Schema::hasColumn('document_versions', 'diff_summary')) {
                $table->json('diff_summary')->nullable();
            }
            if (! Schema::hasColumn('document_versions', 'summary_changed')) {
                $table->text('summary_changed')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('document_versions', function (Blueprint $table) {
            if (Schema::hasColumn('document_versions', 'diff_summary')) {
                $table->dropColumn('diff_summary');
            }
            if (Schema::hasColumn('document_versions', 'summary_changed')) {
                $table->dropColumn('summary_changed');
            }
        });
    }
}
