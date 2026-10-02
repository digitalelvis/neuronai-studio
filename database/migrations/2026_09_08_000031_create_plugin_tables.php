<?php

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $installs = StudioTables::name('plugin_installs');
        $accounts = StudioTables::name('plugin_accounts');
        $bindings = StudioTables::name('agent_plugin_bindings');

        if (! Schema::hasTable($installs)) {
            Schema::create($installs, function (Blueprint $table) {
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
        }

        if (! Schema::hasTable($accounts)) {
            Schema::create($accounts, function (Blueprint $table) use ($installs) {
                $table->id();
                $table->foreignId('plugin_install_id')
                    ->constrained($installs, indexName: 'ns_plugin_acct_install_fk')
                    ->cascadeOnDelete();
                $table->string('label');
                $table->string('auth_status')->default('needs_auth');
                $table->json('credential_map')->nullable();
                $table->timestamps();

                $table->unique(['plugin_install_id', 'label'], 'ns_plugin_acct_install_label_uq');
            });
        }

        if (! Schema::hasTable($bindings)) {
            Schema::create($bindings, function (Blueprint $table) use ($installs, $accounts) {
                $table->id();
                $table->foreignId('agent_definition_id')
                    ->constrained(StudioTables::name('agent_definitions'), indexName: 'ns_agent_plugin_bind_agent_fk')
                    ->cascadeOnDelete();
                $table->foreignId('plugin_install_id')
                    ->constrained($installs, indexName: 'ns_agent_plugin_bind_install_fk')
                    ->cascadeOnDelete();
                $table->foreignId('plugin_account_id')
                    ->nullable()
                    ->constrained($accounts, indexName: 'ns_agent_plugin_bind_account_fk')
                    ->nullOnDelete();
                $table->timestamps();

                $table->unique(['agent_definition_id', 'plugin_install_id'], 'ns_agent_plugin_bind_uq');
            });

            return;
        }

        if (! Schema::hasIndex($bindings, 'ns_agent_plugin_bind_uq', 'unique')) {
            Schema::table($bindings, function (Blueprint $table) {
                $table->unique(['agent_definition_id', 'plugin_install_id'], 'ns_agent_plugin_bind_uq');
            });
        }

        $this->ensureForeignKey($bindings, 'agent_definition_id', StudioTables::name('agent_definitions'), 'ns_agent_plugin_bind_agent_fk');
        $this->ensureForeignKey($bindings, 'plugin_install_id', $installs, 'ns_agent_plugin_bind_install_fk');
        $this->ensureForeignKey($bindings, 'plugin_account_id', $accounts, 'ns_agent_plugin_bind_account_fk', true);
    }

    public function down(): void
    {
        Schema::dropIfExists(StudioTables::name('agent_plugin_bindings'));
        Schema::dropIfExists(StudioTables::name('plugin_accounts'));
        Schema::dropIfExists(StudioTables::name('plugin_installs'));
    }

    private function ensureForeignKey(string $table, string $column, string $references, string $indexName, bool $nullOnDelete = false): void
    {
        $exists = collect(Schema::getForeignKeys($table))
            ->contains(fn (array $foreignKey): bool => in_array($column, $foreignKey['columns'], true));

        if ($exists) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($column, $references, $indexName, $nullOnDelete) {
            $foreignKey = $table->foreign($column, $indexName)
                ->references('id')
                ->on($references);

            if ($nullOnDelete) {
                $foreignKey->nullOnDelete();
            } else {
                $foreignKey->cascadeOnDelete();
            }
        });
    }
};
