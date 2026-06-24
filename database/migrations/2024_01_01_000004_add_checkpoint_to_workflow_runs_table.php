<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->json('checkpoint')->nullable()->after('output');
            $table->string('awaiting_node_id')->nullable()->after('checkpoint');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->dropColumn(['checkpoint', 'awaiting_node_id']);
        });
    }
};
