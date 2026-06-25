<?php

namespace ElvisLopesDigital\NeuronAIStudio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WorkflowDefinition extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'graph',
        'state_schema',
        'status',
        'class_path',
        'source',
        'locked',
    ];

    protected function casts(): array
    {
        return [
            'graph' => 'array',
            'state_schema' => 'array',
            'locked' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WorkflowDefinition $workflow) {
            if (empty($workflow->slug)) {
                $workflow->slug = Str::slug($workflow->name);
            }

            if (empty($workflow->graph)) {
                $workflow->graph = static::defaultGraph();
            }
        });
    }

    public static function defaultGraph(): array
    {
        return [
            'version' => 1,
            'nodes' => [
                [
                    'id' => 'start_1',
                    'type' => 'start',
                    'position' => ['x' => 100, 'y' => 200],
                    'data' => [],
                ],
                [
                    'id' => 'stop_1',
                    'type' => 'stop',
                    'position' => ['x' => 500, 'y' => 200],
                    'data' => [],
                ],
            ],
            'edges' => [
                [
                    'id' => 'edge_1',
                    'source' => 'start_1',
                    'target' => 'stop_1',
                    'sourceHandle' => 'default',
                    'targetHandle' => 'default',
                ],
            ],
            'viewport' => ['x' => 0, 'y' => 0, 'zoom' => 1],
        ];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(WorkflowRun::class, 'workflow_definition_id');
    }

    public function isCodeLinked(): bool
    {
        return $this->source === 'code' && $this->class_path !== null;
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<self>  $query */
    public function scopeStudio($query)
    {
        return $query->where(function ($query) {
            $query->where('source', 'studio')->orWhereNull('source');
        });
    }
}
