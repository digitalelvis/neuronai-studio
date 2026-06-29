<?php

namespace DigitalElvis\NeuronAIStudio\Codegen;

use Illuminate\Support\Str;

class ToolClassGenerator
{
    /**
     * @param  array{
     *     class_name?: string,
     *     tool_name: string,
     *     description: string,
     *     input_schema?: array<int, array<string, mixed>>,
     *     invoke_body?: string,
     *     invoke_params?: string,
     * }  $data
     */
    public function generate(array $data): string
    {
        $namespace = config('neuronai-studio.export_namespace', 'App\\Neuron').'\\Tools';
        $className = $data['class_name'] ?? Str::studly($data['tool_name']).'Tool';
        $toolName = $data['tool_name'];
        $description = addslashes((string) $data['description']);
        $properties = $this->buildPropertiesStub($data['input_schema'] ?? []);
        $invokeParams = $data['invoke_params'] ?? $this->buildInvokeParams($data['input_schema'] ?? []);
        $invokeBody = $this->normalizeInvokeBody($data['invoke_body'] ?? "        return 'Executed';");

        return str_replace(
            ['{{ namespace }}', '{{ className }}', '{{ toolName }}', '{{ description }}', '{{ properties }}', '{{ invokeParams }}', '{{ invokeBody }}'],
            [$namespace, $className, $toolName, $description, $properties, $invokeParams, $invokeBody],
            file_get_contents(__DIR__.'/Stubs/tool.stub')
        );
    }

    /** @param  array<int, array<string, mixed>>  $schema */
    public function buildInvokeParams(array $schema): string
    {
        if ($schema === []) {
            return '';
        }

        return collect($schema)->map(function (array $property) {
            $phpType = match ($property['type'] ?? 'string') {
                'integer' => 'int',
                'number' => 'float',
                'boolean' => 'bool',
                default => 'string',
            };

            return "{$phpType} \${$property['name']}";
        })->implode(', ');
    }

    /** @param  array<int, array<string, mixed>>  $schema */
    protected function buildPropertiesStub(array $schema): string
    {
        if ($schema === []) {
            return '';
        }

        $lines = [];

        foreach ($schema as $property) {
            $enumType = match ($property['type'] ?? 'string') {
                'integer' => 'INTEGER',
                'number' => 'NUMBER',
                'boolean' => 'BOOLEAN',
                default => 'STRING',
            };
            $required = ($property['required'] ?? false) ? 'true' : 'false';
            $lines[] = "            new ToolProperty(
                name: '{$property['name']}',
                type: PropertyType::{$enumType},
                description: '".addslashes((string) ($property['description'] ?? ''))."',
                required: {$required},
            ),";
        }

        return implode("\n", $lines);
    }

    protected function normalizeInvokeBody(string $body): string
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($body)) ?: [];
        $normalized = [];

        foreach ($lines as $line) {
            $trimmed = ltrim($line);

            if ($trimmed === '') {
                $normalized[] = '';

                continue;
            }

            if (! str_starts_with($line, '        ') && ! str_starts_with($line, "\t")) {
                $normalized[] = '        '.$trimmed;
            } else {
                $normalized[] = rtrim($line);
            }
        }

        return implode("\n", $normalized);
    }
}
