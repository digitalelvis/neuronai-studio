<?php

namespace DigitalElvis\NeuronAIStudio\Models;

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class StudioRun extends Model
{
    protected $table;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'thread_id',
        'status',
        'input',
        'output',
        'checkpoint_state',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'error_message',
        'started_at',
        'finished_at',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = StudioTables::name('runs');

        parent::__construct($attributes);
    }

    protected static function booted(): void
    {
        static::creating(function (StudioRun $run) {
            if (empty($run->id)) {
                $run->id = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'output' => 'array',
            'checkpoint_state' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(StudioThread::class, 'thread_id');
    }

    public function traces(): HasMany
    {
        return $this->hasMany(StudioTrace::class, 'run_id');
    }

    public function durationMs(): ?int
    {
        if ($this->started_at === null || $this->finished_at === null) {
            return null;
        }

        return (int) $this->started_at->diffInMilliseconds($this->finished_at);
    }

    public function awaitingNodeId(): ?string
    {
        if (isset($this->checkpoint_state['parallel']['pending_node'])) {
            return $this->checkpoint_state['parallel']['pending_node'];
        }

        return $this->checkpoint_state['node_id'] ?? null;
    }

    public function getWorkflowAttribute()
    {
        return $this->thread?->entity_type === WorkflowDefinition::class
            ? $this->thread->entity
            : null;
    }

    public function steps(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            StudioTraceSpan::class,
            StudioTrace::class,
            'run_id',
            'trace_id',
            'id',
            'id'
        );
    }

    public function getCheckpointAttribute()
    {
        return $this->checkpoint_state;
    }

    public function getAwaitingNodeIdAttribute()
    {
        return $this->awaitingNodeId();
    }

    public function getWorkflowDefinitionIdAttribute()
    {
        return $this->workflow?->id;
    }
}
