<?php

namespace DigitalElvis\NeuronAIStudio\Support;

class StudioTables
{
    /**
     * Canonical unprefixed table keys owned by the package.
     *
     * @var list<string>
     */
    public const TABLES = [
        'agent_definitions',
        'workflow_definitions',
        'tool_definitions',
        'mcp_servers',
        'agent_mcp_server',
        'knowledge_bases',
        'knowledge_documents',
        'threads',
        'runs',
        'traces',
        'trace_spans',
        'chat_messages',
        'eval_suites',
        'eval_runs',
        'eval_run_items',
    ];

    public static function prefix(): string
    {
        return (string) config('neuronai-studio.table_prefix', 'neuronai_studio_');
    }

    public static function name(string $table): string
    {
        return self::prefix().$table;
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_map(fn (string $table) => self::name($table), self::TABLES);
    }
}
