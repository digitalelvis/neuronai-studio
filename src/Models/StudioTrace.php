<?php

namespace DigitalElvis\NeuronAIStudio\Models;

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class StudioTrace extends Model
{
    protected $table;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'run_id',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = StudioTables::name('traces');

        parent::__construct($attributes);
    }

    protected static function booted(): void
    {
        static::creating(function (StudioTrace $trace) {
            if (empty($trace->id)) {
                $trace->id = (string) Str::uuid();
            }
        });
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(StudioRun::class, 'run_id');
    }

    public function spans(): HasMany
    {
        return $this->hasMany(StudioTraceSpan::class, 'trace_id');
    }
}
