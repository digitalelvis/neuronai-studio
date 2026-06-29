<?php

namespace DigitalElvis\NeuronAIStudio\Registry;

use DigitalElvis\NeuronAIStudio\Attributes\StudioGraphReader;
use DigitalElvis\NeuronAIStudio\Contracts\StudioWorkflow;
use Illuminate\Support\Str;
use NeuronAI\Workflow\Workflow;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class WorkflowRegistry
{
    public function __construct(
        protected StudioGraphReader $studioGraphReader,
    ) {}

    /** @return array<int, array{ref: string, label: string, source: string, description: string|null, class_path: string|null, json_path: string|null}> */
    public function codeEntries(): array
    {
        return array_values(array_merge(
            $this->scannedClassEntries(),
            $this->jsonFileEntries(),
        ));
    }

    public function find(string $ref): ?array
    {
        foreach ($this->codeEntries() as $entry) {
            if ($entry['ref'] === $ref) {
                return $entry;
            }
        }

        return null;
    }

    /** @return array<int, string> */
    public function scanWorkflowClasses(): array
    {
        $classes = [];

        foreach (config('neuronai-studio.workflow_scan_paths', []) as $path) {
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

                $reflection = new ReflectionClass($class);

                if ($reflection->isAbstract()) {
                    continue;
                }

                $implementsStudio = in_array(StudioWorkflow::class, class_implements($class) ?: [], true);
                $hasNativeGraph = is_subclass_of($class, Workflow::class)
                    && $this->studioGraphReader->fromClass($class) !== null;

                if (! $implementsStudio && ! $hasNativeGraph) {
                    continue;
                }

                $classes[] = $class;
            }
        }

        sort($classes);

        return array_values(array_unique($classes));
    }

    /** @return array<int, array{ref: string, label: string, source: string, description: string|null, class_path: string|null, json_path: string|null}> */
    protected function scannedClassEntries(): array
    {
        $entries = [];

        foreach ($this->scanWorkflowClasses() as $class) {
            $label = class_basename($class);
            $description = null;

            try {
                $nativeGraph = $this->studioGraphReader->fromClass($class);

                if ($nativeGraph !== null) {
                    $label = $nativeGraph['name'] !== ''
                        ? $nativeGraph['name']
                        : Str::headline(str_replace('Workflow', '', $label));
                    $description = $nativeGraph['description'] !== '' ? $nativeGraph['description'] : null;
                } elseif (in_array(StudioWorkflow::class, class_implements($class) ?: [], true)) {
                    /** @var StudioWorkflow&class-string $class */
                    $meta = $class::studioMeta();
                    $label = (string) ($meta['name'] ?? Str::headline(str_replace('Workflow', '', $label)));
                    $description = (string) ($meta['description'] ?? '');
                }
            } catch (\Throwable) {
                $description = null;
            }

            $entries[] = [
                'ref' => "class:{$class}",
                'label' => $label,
                'source' => 'code',
                'description' => $description ?: null,
                'class_path' => $class,
                'json_path' => null,
            ];
        }

        return $entries;
    }

    /** @return array<int, array{ref: string, label: string, source: string, description: string|null, class_path: string|null, json_path: string|null}> */
    protected function jsonFileEntries(): array
    {
        $entries = [];

        foreach (config('neuronai-studio.workflow_json_paths', []) as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $finder = (new Finder)
                ->files()
                ->in($path)
                ->name('*.json');

            foreach ($finder as $file) {
                $realPath = $file->getRealPath();

                if ($realPath === false) {
                    continue;
                }

                $entries[] = [
                    'ref' => 'json:'.$realPath,
                    'label' => Str::headline($file->getBasename('.json')),
                    'source' => 'json',
                    'description' => null,
                    'class_path' => null,
                    'json_path' => $realPath,
                ];
            }
        }

        usort($entries, fn (array $a, array $b) => strcmp($a['label'], $b['label']));

        return $entries;
    }

    protected function classFromFile(string $file, string $basePath): ?string
    {
        $relative = Str::after($file, rtrim($basePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR);
        $relative = Str::replaceLast('.php', '', $relative);

        $namespace = config('neuronai-studio.export_namespace', 'App\\Neuron');
        $subNamespace = trim(Str::after($basePath, app_path()), DIRECTORY_SEPARATOR);

        if ($subNamespace !== '' && $subNamespace !== 'Neuron') {
            $namespace = 'App\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $subNamespace);
        } elseif (str_contains($basePath, 'Neuron'.DIRECTORY_SEPARATOR.'Workflows')) {
            $namespace = 'App\\Neuron\\Workflows';
        }

        return $namespace.'\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relative);
    }
}
