<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_run_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_run_id')->constrained('workflow_runs')->cascadeOnDelete();
            $table->string('node_id');
            $table->string('node_type');
            $table->string('event_in')->nullable();
            $table->string('event_out')->nullable();
            $table->json('state_snapshot')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_run_steps');
    }
};
