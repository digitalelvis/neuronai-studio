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

        $nodesCode = $this->buildNodesArray($workflow);

        $content = str_replace(
            ['{{ namespace }}', '{{ className }}', '{{ nodes }}'],
            [$namespace, $className, $nodesCode],
            file_get_contents(__DIR__.'/Stubs/workflow.stub')
        );

        $file = $path.'/'.$className.'.php';
        File::put($file, $content);

        return [$file];
    }

    protected function buildNodesArray(WorkflowDefinition $workflow): string
    {
        $nodes = $workflow->graph['nodes'] ?? [];
        $lines = [];

        foreach ($nodes as $node) {
            if (in_array($node['type'], ['start', 'stop'], true)) {
                continue;
            }

            $type = Str::studly($node['type']).'Node';
            $lines[] = "            new {$type}(),";
        }

        if (empty($lines)) {
            return '            // Add exported node classes here';
        }

        return implode("\n", $lines);
    }
}
