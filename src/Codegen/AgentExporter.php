<?php

namespace ElvisLopesDigital\NeuronAIStudio\Codegen;

use ElvisLopesDigital\NeuronAIStudio\Models\AgentDefinition;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class AgentExporter
{
    public function export(AgentDefinition $agent): array
    {
        $namespace = config('neuronai-studio.export_namespace', 'App\\Neuron');
        $path = config('neuronai-studio.export_path', app_path('Neuron'));
        $className = Str::studly($agent->slug).'Agent';

        File::ensureDirectoryExists($path);

        $content = str_replace(
            ['{{ namespace }}', '{{ className }}', '{{ provider }}', '{{ instructions }}'],
            [$namespace, $className, $agent->provider, addslashes((string) $agent->instructions)],
            file_get_contents(__DIR__.'/Stubs/agent.stub')
        );

        $file = $path.'/'.$className.'.php';
        File::put($file, $content);

        return [$file];
    }
}
