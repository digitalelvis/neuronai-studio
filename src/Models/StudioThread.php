<?php

namespace DigitalElvis\NeuronAIStudio\Models;

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use DigitalElvis\NeuronAIStudio\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class StudioThread extends Model
{
    use BelongsToTenant;
    protected $table;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'entity_type',
        'entity_id',
        'ownerable_type',
        'ownerable_id',
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

    public function owner(): MorphTo
    {
        return $this->morphTo('owner', 'ownerable_type', 'ownerable_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(StudioRun::class, 'thread_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(StudioChatMessage::class, 'thread_id');
    }

    /**
     * @param  Builder<StudioThread>  $query
     * @return Builder<StudioThread>
     */
    public function scopeOwnedBy(Builder $query, Model|string $owner, string|int|null $ownerId = null): Builder
    {
        if ($owner instanceof Model) {
            return $query
                ->where('ownerable_type', $owner->getMorphClass())
                ->where('ownerable_id', (string) $owner->getKey());
        }

        if ($ownerId === null || $ownerId === '') {
            throw new \InvalidArgumentException('ownerId is required when owner is a morph type string.');
        }

        return $query
            ->where('ownerable_type', $owner)
            ->where('ownerable_id', (string) $ownerId);
    }
}
