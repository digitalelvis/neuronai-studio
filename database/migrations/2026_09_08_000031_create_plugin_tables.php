<?php

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(StudioTables::name('plugin_installs'), function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->string('tenant_scope')->default('');
            $table->string('slug');
            $table->string('name');
            $table->string('version')->nullable();
            $table->text('description')->nullable();
            $table->json('manifest');
            $table->string('source')->default('catalog');
            $table->string('source_url')->nullable();
            $table->string('source_path')->nullable();
            $table->string('status')->default('installed');
            $table->json('materialized')->nullable();
            $table->timestamps();

            $table->unique(['tenant_scope', 'slug'], 'ns_plugin_inst_tenant_slug_uq');
            $table->index('tenant_id', 'ns_plugin_inst_tenant_id_idx');
        });

        Schema::create(StudioTables::name('plugin_accounts'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('plugin_install_id')
                ->constrained(StudioTables::name('plugin_installs'))
                ->cascadeOnDelete();
            $table->string('label');
            $table->string('auth_status')->default('needs_auth');
            $table->json('credential_map')->nullable();
            $table->timestamps();

            $table->unique(['plugin_install_id', 'label'], 'ns_plugin_acct_install_label_uq');
        });

        Schema::create(StudioTables::name('agent_plugin_bindings'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_definition_id')
                ->constrained(StudioTables::name('agent_definitions'))
                ->cascadeOnDelete();
            $table->foreignId('plugin_install_id')
                ->constrained(StudioTables::name('plugin_installs'))
                ->cascadeOnDelete();
            $table->foreignId('plugin_account_id')
                ->nullable()
                ->constrained(StudioTables::name('plugin_accounts'))
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['agent_definition_id', 'plugin_install_id'], 'ns_agent_plugin_bind_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(StudioTables::name('agent_plugin_bindings'));
        Schema::dropIfExists(StudioTables::name('plugin_accounts'));
        Schema::dropIfExists(StudioTables::name('plugin_installs'));
    }
};
