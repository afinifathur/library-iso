<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDocumentRelationsTable extends Migration
{
    public function up()
    {
        Schema::create('document_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('related_document_id')->constrained('documents')->cascadeOnDelete();
            $table->enum('relation_type', ['references','supersedes','related'])->default('related');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['document_id','related_document_id','relation_type'], 'doc_rel_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('document_relations');
    }
}
