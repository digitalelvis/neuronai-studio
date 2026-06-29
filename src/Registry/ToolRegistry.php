<?php

namespace ElvisLopesDigital\NeuronAIStudio\Registry;

use ElvisLopesDigital\NeuronAIStudio\Models\McpServer;
use ElvisLopesDigital\NeuronAIStudio\Models\ToolDefinition;
use Illuminate\Support\Str;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolInterface;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class ToolRegistry
{
    /** @return array<int, array{ref: string, label: string, type: string, category: string, description: string|null}> */
    public function all(): array
    {
        return array_values(array_merge(
            $this->configEntries(),
            $this->scannedClassEntries(),
            $this->databaseEntries(),
            $this->mcpEntries(),
        ));
    }

    /** @return array<string, array{ref: string, label: string, type: string, category: string, description: string|null}> */
    public function keyed(): array
    {
        return collect($this->all())->keyBy('ref')->all();
    }

    public function find(string $ref): ?array
    {
        return $this->keyed()[$ref] ?? null;
    }

    /** @return array<int, array{ref: string, label: string, type: string, category: string, description: string|null}> */
    protected function configEntries(): array
    {
        $entries = [];

        foreach (config('neuronai-studio.tools', []) as $key => $tool) {
            $entries[] = [
                'ref' => "toolkit:{$key}",
                'label' => $tool['label'] ?? Str::headline($key),
                'type' => $tool['type'] ?? 'toolkit',
                'category' => $tool['category'] ?? 'builtin',
                'description' => $tool['description'] ?? null,
            ];
        }

        return $entries;
    }

    /** @return array<int, array{ref: string, label: string, type: string, category: string, description: string|null}> */
    protected function scannedClassEntries(): array
    {
        $entries = [];
        $exportedPaths = $this->exportedClassPaths();

        foreach ($this->scanToolClasses() as $class) {
            if (in_array($class, $exportedPaths, true)) {
                continue;
            }

            $entries[] = [
                'ref' => "class:{$class}",
                'label' => class_basename($class),
                'type' => 'class',
                'category' => 'app',
                'description' => $this->classDescription($class),
            ];
        }

        return $entries;
    }

    /** @return array<int, string> */
    protected function exportedClassPaths(): array
    {
        if (! class_exists(ToolDefinition::class)) {
            return [];
        }

        try {
            return ToolDefinition::query()
                ->whereNotNull('config')
                ->get()
                ->map(fn (ToolDefinition $tool) => $tool->config['class_path'] ?? null)
                ->filter()
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int, array{ref: string, label: string, type: string, category: string, description: string|null}> */
    protected function databaseEntries(): array
    {
        if (! class_exists(ToolDefinition::class)) {
            return [];
        }

        try {
            return ToolDefinition::query()
                ->orderBy('name')
                ->get()
                ->map(fn (ToolDefinition $tool) => [
                    'ref' => "tool:db:{$tool->id}",
                    'label' => $tool->name,
                    'type' => $tool->type,
                    'category' => 'studio',
                    'description' => $tool->description,
                ])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int, array{ref: string, label: string, type: string, category: string, description: string|null}> */
    protected function mcpEntries(): array
    {
        if (! class_exists(McpRegistry::class)) {
            return $this->legacyMcpEntries();
        }

        try {
            return collect(app(McpRegistry::class)->all())
                ->filter(fn (array $entry) => $entry['enabled'] ?? true)
                ->map(fn (array $entry, string $slug) => [
                    'ref' => "mcp:{$slug}",
                    'label' => $entry['label'] ?? Str::headline($slug),
                    'type' => 'mcp',
                    'category' => 'mcp',
                    'description' => $entry['description'] ?? null,
                ])
                ->values()
                ->all();
        } catch (\Throwable) {
            return $this->legacyMcpEntries();
        }
    }

    /** @return array<int, array{ref: string, label: string, type: string, category: string, description: string|null}> */
    protected function legacyMcpEntries(): array
    {
        $entries = [];

        foreach (config('neuronai-studio.mcp_servers', []) as $key => $server) {
            $entries[] = [
                'ref' => "mcp:{$key}",
                'label' => $server['label'] ?? Str::headline($key),
                'type' => 'mcp',
                'category' => 'mcp',
                'description' => $server['description'] ?? null,
            ];
        }

        return $entries;
    }

    /** @return array<int, string> */
    public function scanToolClasses(): array
    {
        $classes = [];

        foreach (config('neuronai-studio.tool_scan_paths', []) as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $finder = (new Finder)
                ->files()
                ->in($path)
                ->name('*.php');

            foreach ($finder as $file) {
                $class = $this->classFromFile($file->getRealPath(), $path);

                if ($class === null || ! class_exists($class)) {
                    continue;
                }

                if (! is_subclass_of($class, Tool::class) && ! is_subclass_of($class, ToolInterface::class)) {
                    continue;
                }

                if ((new ReflectionClass($class))->isAbstract()) {
                    continue;
                }

                $classes[] = $class;
            }
        }

        sort($classes);

        return array_values(array_unique($classes));
    }

    protected function classFromFile(string $file, string $basePath): ?string
    {
        $relative = Str::after($file, rtrim($basePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
        $relative = Str::replaceLast('.php', '', $relative);

        $namespace = config('neuronai-studio.export_namespace', 'App\\Neuron');
        $subNamespace = trim(Str::after($basePath, app_path()), DIRECTORY_SEPARATOR);

        if ($subNamespace !== '' && $subNamespace !== 'Neuron') {
            $namespace = 'App\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $subNamespace);
        } elseif (str_contains($basePath, 'Neuron'.DIRECTORY_SEPARATOR.'Tools')) {
            $namespace = 'App\\Neuron\\Tools';
        }

        return $namespace.'\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relative);
    }

    protected function classDescription(string $class): ?string
    {
        try {
            $reflection = new ReflectionClass($class);
            $doc = $reflection->getDocComment();

            if ($doc === false) {
                return null;
            }

            if (preg_match('/@description\s+(.+)/', $doc, $matches)) {
                return trim($matches[1]);
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function configFor(string $ref): array
    {
        if (str_starts_with($ref, 'toolkit:')) {
            $key = Str::after($ref, 'toolkit:');

            return config("neuronai-studio.tools.{$key}", []);
        }

        if (str_starts_with($ref, 'mcp:')) {
            $key = Str::after($ref, 'mcp:');

            if (class_exists(McpRegistry::class)) {
                return app(McpRegistry::class)->configFor($key);
            }

            return config("neuronai-studio.mcp_servers.{$key}", []);
        }

        if (str_starts_with($ref, 'tool:db:')) {
            $id = (int) Str::after($ref, 'tool:db:');

            return ToolDefinition::find($id)?->toArray() ?? [];
        }

        if (str_starts_with($ref, 'class:')) {
            return ['class' => Str::after($ref, 'class:')];
        }

        if (str_starts_with($ref, 'provider:')) {
            return ['type' => Str::after($ref, 'provider:')];
        }

        return [];
    }
}
