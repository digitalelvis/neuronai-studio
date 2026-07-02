<?php

namespace DigitalElvis\NeuronAIStudio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** @property-read \Illuminate\Database\Eloquent\Collection<int, KnowledgeDocument> $documents */
class KnowledgeBase extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'embeddings_provider',
        'embeddings_model',
        'vector_store_driver',
        'vector_store_config',
        'retrieval_defaults',
        'metadata',
        'source',
        'class_path',
    ];

    protected function casts(): array
    {
        return [
            'vector_store_config' => 'array',
            'retrieval_defaults' => 'array',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (KnowledgeBase $knowledgeBase) {
            if (empty($knowledgeBase->slug)) {
                $knowledgeBase->slug = Str::slug($knowledgeBase->name);
            }
        });
    }

    public function documents(): HasMany
    {
        return $this->hasMany(KnowledgeDocument::class);
    }

    public function embeddingsProvider(): string
    {
        return $this->embeddings_provider
            ?: (string) config('neuronai-studio.rag.default_embeddings_provider', 'openai');
    }

    public function embeddingsModel(): string
    {
        if (! empty($this->embeddings_model)) {
            return $this->embeddings_model;
        }

        $provider = $this->embeddingsProvider();

        return (string) config(
            "neuronai-studio.rag.embeddings.{$provider}.default_model",
            config('neuronai-studio.rag.default_embeddings_model', 'text-embedding-3-small')
        );
    }

    public function vectorStoreDriver(): string
    {
        return $this->vector_store_driver
            ?: (string) config('neuronai-studio.rag.default_vector_store', 'file');
    }

    public function retrievalDefault(string $key, mixed $default = null): mixed
    {
        return $this->retrieval_defaults[$key]
            ?? config("neuronai-studio.rag.retrieval.{$key}", $default);
    }
}
