<?php

namespace ElvisLopesDigital\NeuronAIStudio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class McpServer extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'transport',
        'command',
        'args',
        'url',
        'token_env',
        'headers',
        'env',
        'only_tools',
        'exclude_tools',
        'timeout',
        'async',
        'enabled',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'args' => 'array',
            'headers' => 'array',
            'env' => 'array',
            'exclude_tools' => 'array',
            'async' => 'boolean',
            'enabled' => 'boolean',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (McpServer $server) {
            if (empty($server->slug)) {
                $server->slug = Str::slug($server->name);
            }
        });
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(AgentDefinition::class, 'agent_mcp_server')
            ->withPivot(['mcp_server_slug', 'only_tools', 'exclude_tools'])
            ->withTimestamps();
    }

    /** @return array<string, mixed> */
    public function toRegistryEntry(): array
    {
        return [
            'label' => $this->name,
            'description' => $this->description,
            'transport' => $this->transport,
            'command' => $this->command,
            'args' => $this->args ?? [],
            'url' => $this->url,
            'token_env' => $this->token_env,
            'headers' => $this->headers ?? [],
            'env' => $this->env ?? [],
            'only_tools' => $this->only_tools,
            'exclude_tools' => $this->exclude_tools ?? [],
            'timeout' => $this->timeout,
            'async' => $this->async,
            'enabled' => $this->enabled,
            'metadata' => $this->metadata ?? [],
        ];
    }
}
