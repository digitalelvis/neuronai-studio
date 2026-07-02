<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_base_id')
                ->constrained('knowledge_bases')
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('source_type')->default('manual');
            $table->string('storage_key')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedInteger('chunk_count')->default(0);
            $table->string('status')->default('pending');
            $table->text('error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_documents');
    }
};
