<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPastedTextToDocumentVersions extends Migration
{
    public function up()
    {
        // add pasted_text safely - do not rely on 'after' to avoid dependency on other migrations
        Schema::table('document_versions', function (Blueprint $table) {
            if (! Schema::hasColumn('document_versions', 'pasted_text')) {
                $table->longText('pasted_text')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('document_versions', function (Blueprint $table) {
            if (Schema::hasColumn('document_versions', 'pasted_text')) {
                $table->dropColumn('pasted_text');
            }
        });
    }
}
