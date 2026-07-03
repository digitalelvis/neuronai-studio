<?php

namespace DigitalElvis\NeuronAIStudio\Registry;

use Illuminate\Support\Str;
use NeuronAI\StructuredOutput\SchemaProperty;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Finder\Finder;

class OutputClassRegistry
{
    /** @return array<int, array{class: string, label: string, properties: array<int, array<string, mixed>>}> */
    public function all(): array
    {
        return array_map(function (string $class): array {
            return [
                'class' => $class,
                'label' => class_basename($class),
                'properties' => $this->classProperties($class),
            ];
        }, $this->scanOutputClasses());
    }

    public function findByShortName(string $name): ?string
    {
        foreach ($this->scanOutputClasses() as $class) {
            if (class_basename($class) === $name) {
                return $class;
            }
        }

        return null;
    }

    public function isValidOutputClass(string $class): bool
    {
        if (! class_exists($class)) {
            return false;
        }

        try {
            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract()) {
                return false;
            }
        } catch (\Throwable) {
            return false;
        }

        return $this->hasSchemaProperties($class);
    }

    /** @return array<int, string> */
    public function scanOutputClasses(): array
    {
        $classes = [];

        foreach (config('neuronai-studio.structured_output_scan_paths', []) as $path) {
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

                if (! $this->isValidOutputClass($class)) {
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
        $classSuffix = str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

        $exportPath = rtrim((string) config('neuronai-studio.export_path', app_path('Neuron')), DIRECTORY_SEPARATOR);
        $exportNamespace = config('neuronai-studio.export_namespace', 'App\\Neuron');

        if (str_starts_with(rtrim($basePath, DIRECTORY_SEPARATOR), $exportPath)) {
            $subPath = Str::after(rtrim($basePath, DIRECTORY_SEPARATOR), $exportPath);
            $subNamespace = trim(str_replace(DIRECTORY_SEPARATOR, '\\', $subPath), '\\');

            return $subNamespace !== ''
                ? $exportNamespace.'\\'.$subNamespace.'\\'.$classSuffix
                : $exportNamespace.'\\'.$classSuffix;
        }

        $subNamespace = trim(Str::after($basePath, app_path()), DIRECTORY_SEPARATOR);

        if ($subNamespace !== '' && $subNamespace !== 'Neuron') {
            $namespace = 'App\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $subNamespace);
        } elseif (str_contains($basePath, 'Neuron'.DIRECTORY_SEPARATOR.'Output')) {
            $namespace = 'App\\Neuron\\Output';
        } else {
            $namespace = $exportNamespace;
        }

        return $namespace.'\\'.$classSuffix;
    }

    protected function hasSchemaProperties(string $class): bool
    {
        return $this->classProperties($class) !== [];
    }

    /** @return array<int, array<string, mixed>> */
    protected function classProperties(string $class): array
    {
        try {
            $reflection = new ReflectionClass($class);
        } catch (\Throwable) {
            return [];
        }

        $properties = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() !== $class) {
                continue;
            }

            $attributes = $property->getAttributes(SchemaProperty::class);

            if ($attributes === []) {
                continue;
            }

            /** @var SchemaProperty $schema */
            $schema = $attributes[0]->newInstance();

            $properties[] = array_filter([
                'name' => $property->getName(),
                'type' => $property->getType()?->getName(),
                'description' => $schema->description,
                'required' => $schema->required,
            ], fn ($value) => $value !== null);
        }

        return $properties;
    }
}
