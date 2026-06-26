<?php

namespace ElvisLopesDigital\NeuronAIStudio\Models;

use ElvisLopesDigital\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class EvalSuite extends Model
{
    protected $table;

    protected $fillable = [
        'agent_definition_id',
        'name',
        'slug',
        'dataset',
        'judge_config',
        'metadata',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = StudioTables::name('eval_suites');

        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'dataset' => 'array',
            'judge_config' => 'array',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EvalSuite $suite) {
            if (empty($suite->slug)) {
                $suite->slug = Str::slug($suite->name);
            }
        });
    }

    public function agentDefinition(): BelongsTo
    {
        return $this->belongsTo(AgentDefinition::class, 'agent_definition_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(EvalRun::class, 'eval_suite_id');
    }
}
