<?php

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(StudioTables::name('agent_mcp_server'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_definition_id')->constrained(StudioTables::name('agent_definitions'))->cascadeOnDelete();
            $table->string('mcp_server_slug');
            $table->foreignId('mcp_server_id')->nullable()->constrained(StudioTables::name('mcp_servers'))->nullOnDelete();
            $table->string('only_tools')->nullable();
            $table->json('exclude_tools')->nullable();
            $table->timestamps();

            $table->unique(['agent_definition_id', 'mcp_server_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(StudioTables::name('agent_mcp_server'));
    }
};
