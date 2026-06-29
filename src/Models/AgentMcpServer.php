<?php

namespace DigitalElvis\NeuronAIStudio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentMcpServer extends Model
{
    protected $table = 'agent_mcp_server';

    protected $fillable = [
        'agent_definition_id',
        'mcp_server_slug',
        'mcp_server_id',
        'only_tools',
        'exclude_tools',
    ];

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
