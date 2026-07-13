<?php

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(StudioTables::name('threads'), function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create(StudioTables::name('runs'), function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('thread_id')->index();
            $table->string('status')->index();
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->json('checkpoint_state')->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->foreign('thread_id')
                ->references('id')
                ->on(StudioTables::name('threads'))
                ->cascadeOnDelete();
        });

        Schema::create(StudioTables::name('traces'), function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('run_id')->index();
            $table->timestamps();

            $table->foreign('run_id')
                ->references('id')
                ->on(StudioTables::name('runs'))
                ->cascadeOnDelete();
        });

        Schema::create(StudioTables::name('trace_spans'), function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('trace_id')->index();
            $table->uuid('parent_span_id')->nullable()->index();
            $table->string('name');
            $table->string('type');
            $table->string('status')->index();
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->foreign('trace_id')
                ->references('id')
                ->on(StudioTables::name('traces'))
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(StudioTables::name('trace_spans'));
        Schema::dropIfExists(StudioTables::name('traces'));
        Schema::dropIfExists(StudioTables::name('runs'));
        Schema::dropIfExists(StudioTables::name('threads'));
    }
};
