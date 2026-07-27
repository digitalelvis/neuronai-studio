<?php

namespace DigitalElvis\NeuronAIStudio\Models;

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property-read KnowledgeBase $knowledgeBase */
class KnowledgeDocument extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $table;

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

    public function __construct(array $attributes = [])
    {
        $this->table = StudioTables::name('knowledge_documents');

        parent::__construct($attributes);
    }

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

    /**
     * Stable vector-store sourceName used for ingest and {@see deleteBy()}.
     */
    public function vectorSourceName(): string
    {
        return 'doc:'.$this->getKey();
    }
}
