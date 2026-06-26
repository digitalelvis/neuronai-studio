<?php

namespace ElvisLopesDigital\NeuronAIStudio\Codegen;

use ElvisLopesDigital\NeuronAIStudio\Attributes\StudioGraphReader;
use ElvisLopesDigital\NeuronAIStudio\Contracts\StudioWorkflow;
use Illuminate\Support\Str;
use NeuronAI\Workflow\Workflow;
use ReflectionClass;

class WorkflowClassImporter
{
    public function __construct(
        protected StudioGraphReader $studioGraphReader,
    ) {}

    /** @return array<string, mixed>|null */
    public function fromClass(string $class): ?array
    {
        if (! class_exists($class)) {
            return [
                'error' => "Class not found: {$class}",
            ];
        }

        $reflection = new ReflectionClass($class);

        if ($reflection->isAbstract()) {
            return [
                'error' => "Cannot import abstract class: {$class}",
            ];
        }

        $nativeGraph = $this->studioGraphReader->fromClass($class);

        if ($nativeGraph !== null && is_subclass_of($class, Workflow::class)) {
            if (empty($nativeGraph['graph']['nodes'])) {
                return [
                    'error' => 'StudioGraph attribute must contain a graph with a nodes key.',
                ];
            }

            return [
                'class_path' => $class,
                'name' => $nativeGraph['name'] !== ''
                    ? $nativeGraph['name']
                    : Str::headline(str_replace('Workflow', '', class_basename($class))),
                'description' => $nativeGraph['description'],
                'status' => $nativeGraph['status'] !== '' ? $nativeGraph['status'] : 'draft',
                'graph' => $nativeGraph['graph'],
                'format' => 'native',
            ];
        }

        if (! in_array(StudioWorkflow::class, class_implements($class) ?: [], true)) {
            if (is_subclass_of($class, Workflow::class)) {
                return [
                    'error' => 'This native Workflow class is missing the #[StudioGraph] attribute required for studio import.',
                ];
            }

            return [
                'error' => 'Class does not implement StudioWorkflow or extend Workflow with #[StudioGraph].',
            ];
        }

        /** @var StudioWorkflow&class-string $class */
        $meta = $class::studioMeta();
        $graph = $class::studioGraph();

        if (! is_array($graph) || empty($graph['nodes'])) {
            return [
                'error' => 'studioGraph() must return an array with a nodes key.',
            ];
        }

        return [
            'class_path' => $class,
            'name' => (string) ($meta['name'] ?? Str::headline(str_replace('Workflow', '', class_basename($class)))),
            'description' => (string) ($meta['description'] ?? ''),
            'status' => (string) ($meta['status'] ?? 'draft'),
            'graph' => $graph,
            'format' => 'legacy',
        ];
    }

    /** @return array<string, mixed>|null */
    public function fromJsonFile(string $path): ?array
    {
        if (! is_readable($path)) {
            return [
                'error' => "JSON file not readable: {$path}",
            ];
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return [
                'error' => "Could not read JSON file: {$path}",
            ];
        }

        try {
            $parsed = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [
                'error' => 'Invalid JSON syntax in workflow file.',
            ];
        }

        return $this->fromParsedJson($parsed, 'json:'.$path, basename($path, '.json'));
    }

    /** @return array<string, mixed>|null */
    public function fromParsedJson(array $parsed, string $ref, ?string $fallbackName = null): ?array
    {
        $graph = $parsed['graph'] ?? (isset($parsed['nodes']) ? $parsed : null);
        $meta = is_array($parsed['meta'] ?? null) ? $parsed['meta'] : [];

        if (! is_array($graph) || empty($graph['nodes'])) {
            return [
                'error' => 'JSON must contain a graph with a nodes array.',
            ];
        }

        $name = (string) ($meta['name'] ?? $fallbackName ?? 'Imported Workflow');

        return [
            'class_path' => $ref,
            'name' => $name,
            'description' => (string) ($meta['description'] ?? ''),
            'status' => (string) ($meta['status'] ?? 'draft'),
            'graph' => $graph,
        ];
    }

    public function hasError(array $result): bool
    {
        return isset($result['error']);
    }

    public function isNativeWorkflow(string $class): bool
    {
        return class_exists($class)
            && is_subclass_of($class, Workflow::class)
            && $this->studioGraphReader->fromClass($class) !== null;
    }
}
