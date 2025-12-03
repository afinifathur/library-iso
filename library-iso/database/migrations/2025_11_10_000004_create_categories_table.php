<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCategoriesTable extends Migration
{
    public function up()
    {
        Schema::create('categories', function (Blueprint $table){
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // add category_id to documents (nullable for existing records)
        Schema::table('documents', function (Blueprint $table){
            $table->foreignId('category_id')->nullable()->after('title')->constrained('categories')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('documents', function (Blueprint $table){
            $table->dropConstrainedForeignId('category_id');
        });
        Schema::dropIfExists('categories');
    }
}
