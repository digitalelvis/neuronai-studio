<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_definition_id')->constrained('workflow_definitions')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_runs');
    }
};
