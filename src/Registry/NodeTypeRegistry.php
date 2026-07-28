<?php

namespace DigitalElvis\NeuronAIStudio\Registry;

use DigitalElvis\NeuronAIStudio\Support\StudioTranslator;
use InvalidArgumentException;

class NodeTypeRegistry
{
    /** @var array<string, array{class: class-string, meta: array}> */
    protected array $types = [];

    public function register(string $type, string $nodeClass, array $meta = []): void
    {
        $configMeta = config("neuronai-studio.node_types.{$type}", []);
        $this->types[$type] = [
            'class' => $nodeClass,
            'meta' => array_merge($configMeta, $meta),
        ];
    }

    public function has(string $type): bool
    {
        return isset($this->types[$type]);
    }

    public function get(string $type): string
    {
        if (! $this->has($type)) {
            throw new InvalidArgumentException("Unknown node type: {$type}");
        }

        return $this->types[$type]['class'];
    }

    public function meta(string $type): array
    {
        return $this->types[$type]['meta'] ?? [];
    }

    /** @return array<string, array{class: class-string, meta: array}> */
    public function all(): array
    {
        return $this->types;
    }

    /** @return array<string, array> */
    public function forCanvas(): array
    {
        $result = [];

        foreach ($this->types as $type => $config) {
            $meta = $config['meta'];
            $meta['label'] = StudioTranslator::get(
                'nodes.'.$type,
                $meta['label'] ?? $type
            );

            if (isset($meta['tool_exposure']['default_description'])) {
                $meta['tool_exposure']['default_description'] = StudioTranslator::get(
                    'nodes.'.$type.'_tool_description',
                    $meta['tool_exposure']['default_description']
                );
            }

            $result[$type] = array_merge(['type' => $type], $meta);
        }

        return $result;
    }
}
