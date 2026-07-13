<?php

namespace DigitalElvis\NeuronAIStudio\Models;

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class StudioThread extends Model
{
    protected $table;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'entity_type',
        'entity_id',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = StudioTables::name('threads');

        parent::__construct($attributes);
    }

    protected static function booted(): void
    {
        static::creating(function (StudioThread $thread) {
            if (empty($thread->id)) {
                $thread->id = (string) Str::uuid();
            }
        });
    }

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    public function runs(): HasMany
    {
        return $this->hasMany(StudioRun::class, 'thread_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(StudioChatMessage::class, 'thread_id');
    }
}
