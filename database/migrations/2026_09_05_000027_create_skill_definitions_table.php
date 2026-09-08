<?php

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(StudioTables::name('skill_definitions'), function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->string('tenant_scope')->default('');
            $table->string('slug');
            $table->text('description');
            $table->string('license')->nullable();
            $table->string('compatibility', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->longText('body')->nullable();
            $table->json('resources')->nullable();
            $table->timestamps();

            $table->unique(['tenant_scope', 'slug'], 'ns_skill_def_tenant_slug_uq');
            $table->index('tenant_id', 'ns_skill_def_tenant_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(StudioTables::name('skill_definitions'));
    }
};
