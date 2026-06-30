<?php

namespace DigitalElvis\NeuronAIStudio\Models;

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTraceStep extends Model
{
    protected $table;

    protected $fillable = [
        'workflow_trace_id',
        'node_id',
        'node_type',
        'event_in',
        'event_out',
        'state_snapshot',
        'duration_ms',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = StudioTables::name('workflow_trace_steps');

        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'state_snapshot' => 'array',
        ];
    }

    public function trace(): BelongsTo
    {
        return $this->belongsTo(WorkflowTrace::class, 'workflow_trace_id');
    }
}
