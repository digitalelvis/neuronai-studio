<?php

namespace ElvisLopesDigital\NeuronAIStudio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowRun extends Model
{
    protected $fillable = [
        'workflow_definition_id',
        'status',
        'input',
        'output',
        'checkpoint',
        'awaiting_node_id',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'output' => 'array',
            'checkpoint' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowRunStep::class)->orderBy('id');
    }
}
