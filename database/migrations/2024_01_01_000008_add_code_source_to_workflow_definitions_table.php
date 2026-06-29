<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_definitions', function (Blueprint $table) {
            $table->string('class_path')->nullable()->unique()->after('status');
            $table->string('source')->default('studio')->after('class_path');
            $table->boolean('locked')->default(false)->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_definitions', function (Blueprint $table) {
            $table->dropUnique(['class_path']);
        });

        Schema::table('workflow_definitions', function (Blueprint $table) {
            $table->dropColumn(['class_path', 'source', 'locked']);
        });
    }
};
