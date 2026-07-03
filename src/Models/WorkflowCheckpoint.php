<?php

namespace DigitalElvis\NeuronAIStudio\Models;

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowCheckpoint extends Model
{
    protected $table;

    protected $fillable = [
        'workflow_trace_id',
        'workflow_key',
        'node_id',
        'iteration',
        'input_hash',
        'state_payload',
        'handle',
        'expires_at',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = StudioTables::name('workflow_checkpoints');

        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'iteration' => 'integer',
            'state_payload' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function trace(): BelongsTo
    {
        return $this->belongsTo(WorkflowTrace::class, 'workflow_trace_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
