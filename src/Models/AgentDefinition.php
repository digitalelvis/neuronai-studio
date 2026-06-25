<?php

namespace ElvisLopesDigital\NeuronAIStudio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** @property-read \Illuminate\Database\Eloquent\Collection<int, AgentMcpServer> $mcpBindings */

class AgentDefinition extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'provider',
        'model',
        'instructions',
        'tools',
        'memory_config',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'tools' => 'array',
            'memory_config' => 'array',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AgentDefinition $agent) {
            if (empty($agent->slug)) {
                $agent->slug = Str::slug($agent->name);
            }
        });
    }

    public function mcpBindings(): HasMany
    {
        return $this->hasMany(AgentMcpServer::class);
    }
}
