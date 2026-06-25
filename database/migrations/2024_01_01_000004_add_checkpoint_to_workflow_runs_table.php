<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workflow_runs')) {
            return;
        }

        Schema::table('workflow_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('workflow_runs', 'checkpoint')) {
                $table->json('checkpoint')->nullable()->after('output');
            }

            if (! Schema::hasColumn('workflow_runs', 'awaiting_node_id')) {
                $table->string('awaiting_node_id')->nullable()->after('checkpoint');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('workflow_runs')) {
            return;
        }

        Schema::table('workflow_runs', function (Blueprint $table) {
            $table->dropColumn(['checkpoint', 'awaiting_node_id']);
        });
    }
};
