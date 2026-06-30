<?php

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(StudioTables::name('eval_suites'), function (Blueprint $table) {
            $table->foreignId('judge_agent_definition_id')
                ->nullable()
                ->after('dataset')
                ->constrained('agent_definitions')
                ->nullOnDelete();
        });

        Schema::table(StudioTables::name('eval_runs'), function (Blueprint $table) {
            $table->foreignId('judge_agent_definition_id')
                ->nullable()
                ->after('model')
                ->constrained('agent_definitions')
                ->nullOnDelete();
            $table->string('judge_provider')->nullable()->after('judge_agent_definition_id');
            $table->string('judge_model')->nullable()->after('judge_provider');
        });
    }

    public function down(): void
    {
        Schema::table(StudioTables::name('eval_runs'), function (Blueprint $table) {
            $table->dropConstrainedForeignId('judge_agent_definition_id');
            $table->dropColumn(['judge_provider', 'judge_model']);
        });

        Schema::table(StudioTables::name('eval_suites'), function (Blueprint $table) {
            $table->dropConstrainedForeignId('judge_agent_definition_id');
        });
    }
};
