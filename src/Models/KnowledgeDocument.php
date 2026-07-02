<?php

namespace DigitalElvis\NeuronAIStudio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property-read KnowledgeBase $knowledgeBase */
class KnowledgeDocument extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'knowledge_base_id',
        'name',
        'source_type',
        'storage_key',
        'mime',
        'chunk_count',
        'status',
        'error',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'chunk_count' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class);
    }
}
