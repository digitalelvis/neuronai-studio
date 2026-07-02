<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_bases', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('embeddings_provider')->nullable();
            $table->string('embeddings_model')->nullable();
            $table->string('vector_store_driver')->nullable();
            $table->json('vector_store_config')->nullable();
            $table->json('retrieval_defaults')->nullable();
            $table->json('metadata')->nullable();
            $table->string('source')->nullable();
            $table->string('class_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_bases');
    }
};
