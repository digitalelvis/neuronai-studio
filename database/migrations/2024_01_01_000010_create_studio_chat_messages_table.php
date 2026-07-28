<?php

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(StudioTables::name('chat_messages'), function (Blueprint $table) {
            $table->id();
            // Scoped keys: agent:{id}:{uuid} / workflow:{id}:{uuid} (not plain UUID).
            $table->string('thread_id', 64)->index();
            $table->string('role');
            $table->json('content');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['thread_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(StudioTables::name('chat_messages'));
    }
};
