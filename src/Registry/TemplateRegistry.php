<?php

namespace DigitalElvis\NeuronAIStudio\Registry;

use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

class TemplateRegistry
{
    /** @return array<int, array<string, mixed>> */
    public function all(?string $type = null, ?string $complexity = null): array
    {
        $entries = array_merge(
            $this->scanType('agent'),
            $this->scanType('workflow'),
        );

        usort($entries, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        if ($type === 'agent') {
            $entries = array_values(array_filter($entries, fn (array $e) => $e['type'] === 'agent'));
        } elseif ($type === 'workflow') {
            $entries = array_values(array_filter($entries, fn (array $e) => $e['type'] === 'workflow'));
        }

        if ($complexity !== null && $complexity !== 'all') {
            $entries = array_values(array_filter(
                $entries,
                fn (array $e) => $e['type'] === 'workflow' && ($e['complexity'] ?? '') === $complexity
            ));
        }

        return $entries;
    }

    /** @return array<string, mixed>|null */
    public function find(string $type, string $id): ?array
    {
        foreach ($this->all() as $entry) {
            if ($entry['type'] === $type && $entry['id'] === $id) {
                return $entry;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public function load(string $type, string $id): ?array
    {
        $entry = $this->find($type, $id);

        if ($entry === null) {
            return null;
        }

        $path = (string) ($entry['path'] ?? '');

        if ($path === '' || ! is_readable($path)) {
            return null;
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return null;
        }

        try {
            $parsed = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($parsed) || ! is_array($parsed['meta'] ?? null)) {
            return null;
        }

        if (($parsed['meta']['id'] ?? null) !== $id) {
            return null;
        }

        return $parsed;
    }

    /** @return array<int, array<string, mixed>> */
    protected function scanType(string $type): array
    {
        $path = $this->pathFor($type);

        if (! is_dir($path)) {
            return [];
        }

        $entries = [];

        foreach ((new Finder)->files()->in($path)->name('*.json') as $file) {
            $parsed = $this->parseFile($file->getRealPath());

            if ($parsed === null) {
                continue;
            }

            $meta = $parsed['meta'];
            $id = (string) ($meta['id'] ?? '');

            if ($id === '') {
                continue;
            }

            $entry = [
                'id' => $id,
                'type' => $type,
                'name' => (string) ($meta['name'] ?? Str::headline($id)),
                'description' => (string) ($meta['description'] ?? ''),
                'category' => (string) ($meta['category'] ?? ''),
                'tags' => is_array($meta['tags'] ?? null) ? $meta['tags'] : [],
                'path' => $file->getRealPath(),
            ];

            if ($type === 'workflow') {
                $entry['complexity'] = (string) ($meta['complexity'] ?? '');
                $entry['agents'] = is_array($meta['agents'] ?? null) ? $meta['agents'] : [];
                $entry['node_types'] = $this->nodeTypesFromGraph($parsed['graph'] ?? []);
            }

            $entries[] = $entry;
        }

        return $entries;
    }

    /** @return array<string, mixed>|null */
    protected function parseFile(string $path): ?array
    {
        if (! is_readable($path)) {
            return null;
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return null;
        }

        try {
            $parsed = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($parsed) || ! is_array($parsed['meta'] ?? null)) {
            return null;
        }

        if ($type = $this->typeFromPath($path)) {
            if ($type === 'agent' && ! is_array($parsed['definition'] ?? null)) {
                return null;
            }

            if ($type === 'workflow' && ! is_array($parsed['graph'] ?? null)) {
                return null;
            }
        }

        return $parsed;
    }

    /** @return array<int, string> */
    protected function nodeTypesFromGraph(array $graph): array
    {
        $types = [];

        foreach ($graph['nodes'] ?? [] as $node) {
            $type = (string) ($node['type'] ?? '');

            if ($type !== '' && ! in_array($type, ['start', 'stop'], true)) {
                $types[] = $type;
            }
        }

        return array_values(array_unique($types));
    }

    protected function pathFor(string $type): string
    {
        $configured = config("neuronai-studio.template_paths.{$type}");

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return dirname(__DIR__, 2)."/resources/templates/{$type}s";
    }

    protected function typeFromPath(string $path): ?string
    {
        if (str_contains($path, '/templates/agents/')) {
            return 'agent';
        }

        if (str_contains($path, '/templates/workflows/')) {
            return 'workflow';
        }

        return null;
    }
}
