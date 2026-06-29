<?php

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tracesTable = StudioTables::name('workflow_traces');
        $stepsTable = StudioTables::name('workflow_trace_steps');

        if (! Schema::hasTable('workflow_runs')) {
            return;
        }

        if (! Schema::hasTable($tracesTable)) {
            Schema::create($tracesTable, function (Blueprint $table) {
                $table->id();
                $table->foreignId('workflow_definition_id')->constrained('workflow_definitions')->cascadeOnDelete();
                $table->string('status')->default('pending');
                $table->json('input')->nullable();
                $table->json('output')->nullable();
                $table->json('checkpoint')->nullable();
                $table->string('awaiting_node_id')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($stepsTable)) {
            Schema::create($stepsTable, function (Blueprint $table) use ($tracesTable) {
                $table->id();
                $table->foreignId('workflow_trace_id')
                    ->constrained($tracesTable)
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

        if (DB::table($tracesTable)->count() === 0) {
            DB::table('workflow_runs')->orderBy('id')->chunk(100, function ($runs) use ($tracesTable) {
                foreach ($runs as $run) {
                    DB::table($tracesTable)->insert([
                        'id' => $run->id,
                        'workflow_definition_id' => $run->workflow_definition_id,
                        'status' => $run->status,
                        'input' => $run->input,
                        'output' => $run->output,
                        'checkpoint' => $run->checkpoint ?? null,
                        'awaiting_node_id' => $run->awaiting_node_id ?? null,
                        'error_message' => $run->error_message,
                        'started_at' => $run->started_at,
                        'finished_at' => $run->finished_at,
                        'created_at' => $run->created_at,
                        'updated_at' => $run->updated_at,
                    ]);
                }
            });
        }

        if (Schema::hasTable('workflow_run_steps') && DB::table($stepsTable)->count() === 0) {
            DB::table('workflow_run_steps')->orderBy('id')->chunk(100, function ($steps) use ($stepsTable) {
                foreach ($steps as $step) {
                    DB::table($stepsTable)->insert([
                        'id' => $step->id,
                        'workflow_trace_id' => $step->workflow_run_id,
                        'node_id' => $step->node_id,
                        'node_type' => $step->node_type,
                        'event_in' => $step->event_in,
                        'event_out' => $step->event_out,
                        'state_snapshot' => $step->state_snapshot,
                        'duration_ms' => $step->duration_ms,
                        'created_at' => $step->created_at,
                        'updated_at' => $step->updated_at,
                    ]);
                }
            });
        }

        Schema::dropIfExists('workflow_run_steps');
        Schema::dropIfExists('workflow_runs');
    }

    public function down(): void
    {
        // Irreversible data migration.
    }
};
