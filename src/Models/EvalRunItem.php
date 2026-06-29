<?php

namespace DigitalElvis\NeuronAIStudio\Models;

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvalRunItem extends Model
{
    protected $table;

    protected $fillable = [
        'eval_run_id',
        'case_index',
        'input',
        'output',
        'passed',
        'failures',
        'scores',
        'execution_time_ms',
        'error_message',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = StudioTables::name('eval_run_items');

        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'passed' => 'boolean',
            'failures' => 'array',
            'scores' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(EvalRun::class, 'eval_run_id');
    }
}
