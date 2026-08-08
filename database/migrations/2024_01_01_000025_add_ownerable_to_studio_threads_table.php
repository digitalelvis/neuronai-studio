<?php

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(StudioTables::name('threads'), function (Blueprint $table) {
            $table->string('ownerable_type')->nullable()->after('entity_id');
            $table->string('ownerable_id')->nullable()->after('ownerable_type');

            $table->index(['ownerable_type', 'ownerable_id'], 'neuronai_studio_threads_ownerable_index');
        });
    }

    public function down(): void
    {
        Schema::table(StudioTables::name('threads'), function (Blueprint $table) {
            $table->dropIndex('neuronai_studio_threads_ownerable_index');
            $table->dropColumn(['ownerable_type', 'ownerable_id']);
        });
    }
};
