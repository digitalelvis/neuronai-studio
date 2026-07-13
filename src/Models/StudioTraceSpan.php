<?php

namespace DigitalElvis\NeuronAIStudio\Models;

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StudioTraceSpan extends Model
{
    protected $table;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'trace_id',
        'parent_span_id',
        'name',
        'type',
        'status',
        'input',
        'output',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'duration_ms',
        'error_message',
        'started_at',
        'finished_at',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = StudioTables::name('trace_spans');

        parent::__construct($attributes);
    }

    protected static function booted(): void
    {
        static::creating(function (StudioTraceSpan $span) {
            if (empty($span->id)) {
                $span->id = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'output' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    public function trace(): BelongsTo
    {
        return $this->belongsTo(StudioTrace::class, 'trace_id');
    }

    public function parentSpan(): BelongsTo
    {
        return $this->belongsTo(StudioTraceSpan::class, 'parent_span_id');
    }

    public function getNodeIdAttribute()
    {
        return $this->name;
    }

    public function getNodeTypeAttribute()
    {
        return $this->type;
    }

    public function getStateSnapshotAttribute()
    {
        return $this->output['state_snapshot'] ?? null;
    }
}
