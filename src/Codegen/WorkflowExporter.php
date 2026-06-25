<?php

namespace ElvisLopesDigital\NeuronAIStudio\Codegen;

use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowDefinition;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class WorkflowExporter
{
    public function export(WorkflowDefinition $workflow): array
    {
        $namespace = config('neuronai-studio.export_namespace', 'App\\Neuron');
        $path = config('neuronai-studio.export_path', app_path('Neuron'));
        $className = Str::studly($workflow->slug).'Workflow';

        File::ensureDirectoryExists($path);

        $meta = [
            'name' => $workflow->name,
            'description' => (string) ($workflow->description ?? ''),
            'status' => $workflow->status,
        ];

        $content = str_replace(
            ['{{ namespace }}', '{{ className }}', '{{ meta }}', '{{ graph }}'],
            [
                $namespace,
                $className,
                $this->exportArray($meta, 2),
                $this->exportArray($workflow->graph ?? WorkflowDefinition::defaultGraph(), 2),
            ],
            file_get_contents(__DIR__.'/Stubs/studio-workflow.stub')
        );

        $file = $path.'/'.$className.'.php';
        File::put($file, $content);

        return [$file];
    }

    protected function exportArray(array $data, int $indent = 0): string
    {
        $pad = str_repeat('    ', $indent);
        $inner = str_repeat('    ', $indent + 1);
        $lines = ["{$pad}["];

        foreach ($data as $key => $value) {
            $exportedKey = is_int($key) ? $key : var_export($key, true);
            $lines[] = "{$inner}{$exportedKey} => ".$this->exportValue($value, $indent + 1).',';
        }

        $lines[] = "{$pad}]";

        return implode("\n", $lines);
    }

    protected function exportValue(mixed $value, int $indent): string
    {
        if (is_array($value)) {
            return $this->exportArray($value, $indent);
        }

        return var_export($value, true);
    }
}
