<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_definitions', function (Blueprint $table) {
            $table->unsignedInteger('tool_max_runs')->nullable()->after('require_tool_approval');
            $table->boolean('parallel_tool_calls')->nullable()->after('tool_max_runs');
        });
    }

    public function down(): void
    {
        Schema::table('agent_definitions', function (Blueprint $table) {
            $table->dropColumn(['tool_max_runs', 'parallel_tool_calls']);
        });
    }
};
