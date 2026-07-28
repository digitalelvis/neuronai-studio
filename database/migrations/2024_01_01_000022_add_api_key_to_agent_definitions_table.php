<?php

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(StudioTables::name('agent_definitions'), function (Blueprint $table) {
            $table->string('api_key')->nullable()->after('model');
        });
    }

    public function down(): void
    {
        Schema::table(StudioTables::name('agent_definitions'), function (Blueprint $table) {
            $table->dropColumn('api_key');
        });
    }
};
