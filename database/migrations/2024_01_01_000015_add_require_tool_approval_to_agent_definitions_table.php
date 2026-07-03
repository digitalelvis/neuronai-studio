<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_definitions', function (Blueprint $table) {
            $table->boolean('require_tool_approval')->default(false)->after('tools');
        });
    }

    public function down(): void
    {
        Schema::table('agent_definitions', function (Blueprint $table) {
            $table->dropColumn('require_tool_approval');
        });
    }
};
