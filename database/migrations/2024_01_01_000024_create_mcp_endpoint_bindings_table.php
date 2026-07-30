<?php

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(StudioTables::name('mcp_endpoint_bindings'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('mcp_endpoint_id')
                ->constrained(StudioTables::name('mcp_endpoints'))
                ->cascadeOnDelete();
            $table->string('kind'); // tool | toolkit | agent | workflow
            $table->string('ref');
            $table->string('tool_name')->nullable();
            $table->text('tool_description')->nullable();
            $table->json('only')->nullable();
            $table->json('exclude')->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['mcp_endpoint_id', 'enabled']);
            $table->unique(['mcp_endpoint_id', 'kind', 'ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(StudioTables::name('mcp_endpoint_bindings'));
    }
};
