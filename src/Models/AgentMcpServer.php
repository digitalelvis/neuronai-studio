<?php

namespace DigitalElvis\NeuronAIStudio\Models;

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentMcpServer extends Model
{
    protected $table;

    protected $fillable = [
        'agent_definition_id',
        'mcp_server_slug',
        'mcp_server_id',
        'only_tools',
        'exclude_tools',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = StudioTables::name('agent_mcp_server');

        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'exclude_tools' => 'array',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AgentDefinition::class, 'agent_definition_id');
    }

    public function mcpServer(): BelongsTo
    {
        return $this->belongsTo(McpServer::class, 'mcp_server_id');
    }
}
