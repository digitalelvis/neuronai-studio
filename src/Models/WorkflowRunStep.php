<?php

namespace ElvisLopesDigital\NeuronAIStudio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowRunStep extends Model
{
    protected $fillable = [
        'workflow_run_id',
        'node_id',
        'node_type',
        'event_in',
        'event_out',
        'state_snapshot',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'state_snapshot' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(WorkflowRun::class, 'workflow_run_id');
    }
}
