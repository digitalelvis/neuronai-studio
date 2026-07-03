<?php

namespace DigitalElvis\NeuronAIStudio\Tools;

use DigitalElvis\NeuronAIStudio\Models\KnowledgeBase;
use DigitalElvis\NeuronAIStudio\Models\ToolDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\Rag\RagRetrievalService;
use Illuminate\Support\Str;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\ToolPropertyInterface;

class KnowledgeBaseTool extends Tool
{
    /** @param  array<string, mixed>  $config */
    public function __construct(
        string $name,
        string $description,
        protected array $config,
        array $properties = [],
    ) {
        parent::__construct($name, $description, $properties);
        $this->setCallable(fn (string $query) => $this->executeSearch($query));
    }

    public static function fromDefinition(ToolDefinition $definition): self
    {
        $properties = self::buildProperties($definition->input_schema ?? []);

        return new self(
            Str::slug($definition->config['tool_name'] ?? $definition->slug, '_'),
            $definition->description,
            $definition->config ?? [],
            $properties,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $schema
     * @return array<int, ToolPropertyInterface>
     */
    public static function buildProperties(array $schema): array
    {
        if ($schema === []) {
            return self::defaultQueryProperty();
        }

        return self::buildPropertiesFromSchema($schema);
    }

    /**
     * @return array<int, ToolPropertyInterface>
     */
    protected static function defaultQueryProperty(): array
    {
        return [
            new ToolProperty(
                name: 'query',
                type: PropertyType::STRING,
                description: 'Natural language search query',
                required: true,
            ),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $schema
     * @return array<int, ToolPropertyInterface>
     */
    protected static function buildPropertiesFromSchema(array $schema): array
    {
        $properties = [];

        foreach ($schema as $property) {
            if (empty($property['name'])) {
                continue;
            }

            $properties[] = new ToolProperty(
                name: $property['name'],
                type: PropertyType::from($property['type'] ?? 'string'),
                description: $property['description'] ?? '',
                required: (bool) ($property['required'] ?? false),
            );
        }

        return $properties;
    }

    public function __invoke(string $query): string
    {
        return $this->executeSearch($query);
    }

    protected function executeSearch(string $query): string
    {
        $query = trim($query);

        if ($query === '') {
            return 'Error: query parameter is required.';
        }

        $knowledgeBaseId = $this->config['knowledge_base_id'] ?? null;

        if (empty($knowledgeBaseId)) {
            return 'Error: knowledge base is not configured for this tool.';
        }

        $knowledgeBase = KnowledgeBase::find($knowledgeBaseId);

        if ($knowledgeBase === null) {
            return 'Error: knowledge base not found.';
        }

        $options = [];

        if (isset($this->config['top_k']) && $this->config['top_k'] !== '' && $this->config['top_k'] !== null) {
            $options['top_k'] = (int) $this->config['top_k'];
        }

        if (isset($this->config['threshold']) && $this->config['threshold'] !== '' && $this->config['threshold'] !== null) {
            $options['threshold'] = (float) $this->config['threshold'];
        }

        $retrieval = app(RagRetrievalService::class);
        $results = $retrieval->search($knowledgeBase, $query, $options);

        if ($results === []) {
            return 'No matching documents found in the knowledge base.';
        }

        return $this->formatContext($results);
    }

    /**
     * @param  list<array{content: string, source_name?: string}>  $results
     */
    protected function formatContext(array $results): string
    {
        $parts = [];

        foreach ($results as $result) {
            $source = (string) ($result['source_name'] ?? 'document');
            $content = (string) ($result['content'] ?? '');
            $parts[] = "[{$source}]\n{$content}";
        }

        return implode("\n\n---\n\n", $parts);
    }
}
