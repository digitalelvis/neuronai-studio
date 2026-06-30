<?php

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(StudioTables::name('workflow_trace_steps'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_trace_id')
                ->constrained(StudioTables::name('workflow_traces'))
                ->cascadeOnDelete();
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
        Schema::dropIfExists(StudioTables::name('workflow_trace_steps'));
    }
};
