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

        $toolsMethod = $this->buildToolsMethod($agent->tools ?? []);

        $content = str_replace(
            ['{{ namespace }}', '{{ className }}', '{{ provider }}', '{{ instructions }}', '{{ toolsMethod }}'],
            [$namespace, $className, $agent->provider, addslashes((string) $agent->instructions), $toolsMethod],
            file_get_contents(__DIR__.'/Stubs/agent.stub')
        );

        $file = $path.'/'.$className.'.php';
        File::put($file, $content);

        return [$file];
    }

    /** @param  array<int, array<string, mixed>>  $tools */
    protected function buildToolsMethod(array $tools): string
    {
        if ($tools === []) {
            return <<<'PHP'
    protected function tools(): array
    {
        return [];
    }
PHP;
        }

        $entries = collect($tools)->map(function (array $binding) {
            $ref = $binding['ref'] ?? '';
            $lines = [];

            if (str_starts_with($ref, 'toolkit:')) {
                $class = config('neuronai-studio.tools.'.Str::after($ref, 'toolkit:').'.class');
                $lines[] = "            \\{$class}::make(),";
            } elseif (str_starts_with($ref, 'class:')) {
                $class = Str::after($ref, 'class:');
                $lines[] = "            \\{$class}::make(),";
            } elseif (str_starts_with($ref, 'tool:db:')) {
                $lines[] = "            // tool:db binding — export PHP class from studio tool first";
            } elseif (str_starts_with($ref, 'mcp:')) {
                $lines[] = "            // mcp:".Str::after($ref, 'mcp:')." — configure McpConnector manually";
            }

            return implode("\n", $lines);
        })->filter()->implode("\n");

        return <<<PHP
    protected function tools(): array
    {
        return [
{$entries}
        ];
    }
PHP;
    }
}
