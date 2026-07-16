<?php

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(StudioTables::name('trace_spans'), function (Blueprint $table) {
            $table->string('provider')->nullable()->after('type');
            $table->string('model')->nullable()->after('provider');
            $table->decimal('estimated_cost', 12, 6)->default(0)->after('total_tokens');
        });

        Schema::table(StudioTables::name('runs'), function (Blueprint $table) {
            $table->decimal('estimated_cost', 12, 6)->default(0)->after('total_tokens');
            $table->uuid('parent_run_id')->nullable()->index()->after('thread_id');
            $table->foreign('parent_run_id')
                ->references('id')
                ->on(StudioTables::name('runs'))
                ->nullOnDelete();
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::table(StudioTables::name('runs'), function (Blueprint $table) {
            $table->dropForeign(['parent_run_id']);
        });

        Schema::table(StudioTables::name('runs'), function (Blueprint $table) {
            $table->dropIndex(['parent_run_id']);
            $table->dropIndex(['started_at']);
            $table->dropColumn(['estimated_cost', 'parent_run_id']);
        });

        Schema::table(StudioTables::name('trace_spans'), function (Blueprint $table) {
            $table->dropColumn(['provider', 'model', 'estimated_cost']);
        });
    }
};
