<?php

use ElvisLopesDigital\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(StudioTables::name('eval_suites'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_definition_id')->constrained('agent_definitions')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->json('dataset');
            $table->json('judge_config')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['agent_definition_id', 'slug']);
        });

        Schema::create(StudioTables::name('eval_runs'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('eval_suite_id')->constrained(StudioTables::name('eval_suites'))->cascadeOnDelete();
            $table->foreignId('agent_definition_id')->constrained('agent_definitions')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('passed_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->decimal('success_rate', 5, 4)->default(0);
            $table->unsignedInteger('total_time_ms')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create(StudioTables::name('eval_run_items'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('eval_run_id')->constrained(StudioTables::name('eval_runs'))->cascadeOnDelete();
            $table->unsignedInteger('case_index');
            $table->json('input');
            $table->text('output')->nullable();
            $table->boolean('passed')->default(false);
            $table->json('failures')->nullable();
            $table->json('scores')->nullable();
            $table->unsignedInteger('execution_time_ms')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(StudioTables::name('eval_run_items'));
        Schema::dropIfExists(StudioTables::name('eval_runs'));
        Schema::dropIfExists(StudioTables::name('eval_suites'));
    }
};
