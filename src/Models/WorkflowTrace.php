<?php

namespace DigitalElvis\NeuronAIStudio\Models;

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowTrace extends Model
{
    protected $table;

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

    public function __construct(array $attributes = [])
    {
        $this->table = StudioTables::name('workflow_traces');

        parent::__construct($attributes);
    }

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
        return $this->hasMany(WorkflowTraceStep::class, 'workflow_trace_id')->orderBy('id');
    }

    public function durationMs(): ?int
    {
        if ($this->started_at === null || $this->finished_at === null) {
            return null;
        }

        return (int) $this->started_at->diffInMilliseconds($this->finished_at);
    }
}
