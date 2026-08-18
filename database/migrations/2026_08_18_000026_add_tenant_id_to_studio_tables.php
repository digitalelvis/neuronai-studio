<?php

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, array{identifier: string, unique: string, tenant_idx: string}>
     */
    private const SLUG_TABLES = [
        'agent_definitions' => [
            'identifier' => 'slug',
            'unique' => 'ns_agent_def_tenant_slug_uq',
            'tenant_idx' => 'ns_agent_def_tenant_id_idx',
        ],
        'workflow_definitions' => [
            'identifier' => 'slug',
            'unique' => 'ns_workflow_def_tenant_slug_uq',
            'tenant_idx' => 'ns_workflow_def_tenant_id_idx',
        ],
        'knowledge_bases' => [
            'identifier' => 'slug',
            'unique' => 'ns_kb_tenant_slug_uq',
            'tenant_idx' => 'ns_kb_tenant_id_idx',
        ],
        'tool_definitions' => [
            'identifier' => 'slug',
            'unique' => 'ns_tool_def_tenant_slug_uq',
            'tenant_idx' => 'ns_tool_def_tenant_id_idx',
        ],
        'mcp_servers' => [
            'identifier' => 'slug',
            'unique' => 'ns_mcp_server_tenant_slug_uq',
            'tenant_idx' => 'ns_mcp_server_tenant_id_idx',
        ],
        'mcp_endpoints' => [
            'identifier' => 'slug',
            'unique' => 'ns_mcp_ep_tenant_slug_uq',
            'tenant_idx' => 'ns_mcp_ep_tenant_id_idx',
        ],
        'variables' => [
            'identifier' => 'name',
            'unique' => 'ns_variables_tenant_name_uq',
            'tenant_idx' => 'ns_variables_tenant_id_idx',
        ],
    ];

    /**
     * @var array<string, string>
     */
    private const RUNTIME_TABLES = [
        'threads' => 'ns_threads_tenant_id_idx',
        'runs' => 'ns_runs_tenant_id_idx',
        'traces' => 'ns_traces_tenant_id_idx',
    ];

    public function up(): void
    {
        foreach (self::SLUG_TABLES as $logical => $meta) {
            $table = StudioTables::name($logical);
            $identifier = $meta['identifier'];
            $unique = $meta['unique'];
            $tenantIdx = $meta['tenant_idx'];

            Schema::table($table, function (Blueprint $blueprint) use ($tenantIdx) {
                $blueprint->string('tenant_id')->nullable();
                $blueprint->string('tenant_scope')->default('');
                $blueprint->index('tenant_id', $tenantIdx);
            });

            Schema::table($table, function (Blueprint $blueprint) use ($identifier) {
                $blueprint->dropUnique([$identifier]);
            });

            Schema::table($table, function (Blueprint $blueprint) use ($identifier, $unique) {
                $blueprint->unique(['tenant_scope', $identifier], $unique);
            });
        }

        foreach (self::RUNTIME_TABLES as $logical => $tenantIdx) {
            Schema::table(StudioTables::name($logical), function (Blueprint $blueprint) use ($tenantIdx) {
                $blueprint->string('tenant_id')->nullable();
                $blueprint->string('tenant_scope')->default('');
                $blueprint->index('tenant_id', $tenantIdx);
            });
        }
    }

    public function down(): void
    {
        foreach (self::SLUG_TABLES as $logical => $meta) {
            $table = StudioTables::name($logical);
            $unique = $meta['unique'];
            $tenantIdx = $meta['tenant_idx'];

            Schema::table($table, function (Blueprint $blueprint) use ($unique) {
                $blueprint->dropUnique($unique);
            });

            Schema::table($table, function (Blueprint $blueprint) use ($tenantIdx) {
                $blueprint->dropIndex($tenantIdx);
                $blueprint->dropColumn(['tenant_id', 'tenant_scope']);
            });
        }

        foreach (self::RUNTIME_TABLES as $logical => $tenantIdx) {
            Schema::table(StudioTables::name($logical), function (Blueprint $blueprint) use ($tenantIdx) {
                $blueprint->dropIndex($tenantIdx);
                $blueprint->dropColumn(['tenant_id', 'tenant_scope']);
            });
        }
    }
};
