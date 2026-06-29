<?php

namespace ElvisLopesDigital\NeuronAIStudio\Models;

use ElvisLopesDigital\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvalRun extends Model
{
    protected $table;

    protected $fillable = [
        'eval_suite_id',
        'agent_definition_id',
        'status',
        'provider',
        'model',
        'judge_agent_definition_id',
        'judge_provider',
        'judge_model',
        'passed_count',
        'failed_count',
        'success_rate',
        'total_time_ms',
        'started_at',
        'finished_at',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = StudioTables::name('eval_runs');

        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'success_rate' => 'float',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function suite(): BelongsTo
    {
        return $this->belongsTo(EvalSuite::class, 'eval_suite_id');
    }

    public function agentDefinition(): BelongsTo
    {
        return $this->belongsTo(AgentDefinition::class, 'agent_definition_id');
    }

    public function judgeAgent(): BelongsTo
    {
        return $this->belongsTo(AgentDefinition::class, 'judge_agent_definition_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(EvalRunItem::class, 'eval_run_id')->orderBy('case_index');
    }
}
