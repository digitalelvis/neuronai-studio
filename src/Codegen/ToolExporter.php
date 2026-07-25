<?php

namespace DigitalElvis\NeuronAIStudio\Codegen;

use DigitalElvis\NeuronAIStudio\Models\ToolDefinition;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ToolExporter
{
    public function __construct(
        protected ToolClassGenerator $generator,
    ) {}

    public function export(ToolDefinition $tool): array
    {
        CodegenGuard::ensureExport();

        $namespace = config('neuronai-studio.export_namespace', 'App\\Neuron').'\\Tools';
        $path = config('neuronai-studio.export_path', app_path('Neuron')).'/Tools';
        $className = $tool->config['class_name'] ?? Str::studly($tool->slug).'Tool';

        File::ensureDirectoryExists($path);

        $content = $this->generator->generate([
            'class_name' => $className,
            'tool_name' => $tool->config['tool_name'] ?? Str::slug($tool->slug, '_'),
            'description' => $tool->description,
            'input_schema' => $tool->input_schema ?? [],
            'invoke_body' => $tool->config['invoke_body'] ?? "        return 'Executed';",
        ]);

        $file = $path.'/'.$className.'.php';
        File::put($file, $content);

        $tool->update([
            'type' => 'builder',
            'config' => array_merge($tool->config ?? [], [
                'class_name' => $className,
                'class_path' => $namespace.'\\'.$className,
                'tool_name' => $tool->config['tool_name'] ?? Str::slug($tool->slug, '_'),
            ]),
        ]);

        return [$file];
    }
}
