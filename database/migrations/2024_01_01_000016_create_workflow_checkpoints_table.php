<?php

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $checkpointsTable = StudioTables::name('workflow_checkpoints');
        $tracesTable = StudioTables::name('workflow_traces');

        if (Schema::hasTable($checkpointsTable)) {
            return;
        }

        Schema::create($checkpointsTable, function (Blueprint $table) use ($tracesTable) {
            $table->id();
            $table->foreignId('workflow_trace_id')
                ->nullable()
                ->constrained($tracesTable)
                ->cascadeOnDelete();
            $table->string('workflow_key')->nullable();
            $table->string('node_id');
            $table->unsignedInteger('iteration')->default(0);
            $table->string('input_hash', 64)->nullable();
            $table->json('state_payload')->nullable();
            $table->string('handle')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['workflow_trace_id', 'node_id', 'iteration']);
            $table->index('workflow_key');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(StudioTables::name('workflow_checkpoints'));
    }
};
