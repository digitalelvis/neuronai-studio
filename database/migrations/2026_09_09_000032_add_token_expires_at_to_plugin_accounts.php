<?php

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(StudioTables::name('plugin_accounts'), function (Blueprint $table) {
            $table->timestamp('token_expires_at')->nullable()->after('credential_map');
        });
    }

    public function down(): void
    {
        Schema::table(StudioTables::name('plugin_accounts'), function (Blueprint $table) {
            $table->dropColumn('token_expires_at');
        });
    }
};
