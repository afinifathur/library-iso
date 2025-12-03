<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPlainTextToDocumentVersionsFix extends Migration
{
    public function up()
    {
        Schema::table('document_versions', function (Blueprint $table) {
            // tambahkan kolom plain_text jika belum ada
            if (! Schema::hasColumn('document_versions', 'plain_text')) {
                $table->longText('plain_text')->nullable()->after('change_note');
            }
        });
    }

    public function down()
    {
        Schema::table('document_versions', function (Blueprint $table) {
            if (Schema::hasColumn('document_versions', 'plain_text')) {
                $table->dropColumn('plain_text');
            }
        });
    }
}
