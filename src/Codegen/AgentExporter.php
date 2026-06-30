<?php

namespace DigitalElvis\NeuronAIStudio\Codegen;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Registry\McpRegistry;
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

        $agent->loadMissing('mcpBindings');
        $toolsMethod = $this->buildToolsMethod($agent);

        $content = str_replace(
            ['{{ namespace }}', '{{ className }}', '{{ provider }}', '{{ instructions }}', '{{ toolsMethod }}', '{{ mcpUse }}'],
            [
                $namespace,
                $className,
                $agent->provider,
                addslashes((string) $agent->instructions),
                $toolsMethod,
                $this->needsMcpConnector($agent) ? "use NeuronAI\\MCP\\McpConnector;\n" : '',
            ],
            file_get_contents(__DIR__.'/Stubs/agent.stub')
        );

        $file = $path.'/'.$className.'.php';
        File::put($file, $content);

        return [$file];
    }

    protected function needsMcpConnector(AgentDefinition $agent): bool
    {
        if ($agent->mcpBindings->isNotEmpty()) {
            return true;
        }

        return collect($agent->tools ?? [])->contains(fn (array $binding) => str_starts_with($binding['ref'] ?? '', 'mcp:'));
    }

    protected function buildToolsMethod(AgentDefinition $agent): string
    {
        $entries = collect($agent->tools ?? [])
            ->map(fn (array $binding) => $this->toolBindingLine($binding))
            ->filter()
            ->merge(
                $agent->mcpBindings->map(fn ($binding) => $this->mcpBindingLine(
                    $binding->mcp_server_slug,
                    $binding->only_tools,
                    $binding->exclude_tools ?? [],
                ))
            )
            ->unique()
            ->implode("\n");

        if ($entries === '') {
            return <<<'PHP'
    protected function tools(): array
    {
        return [];
    }
PHP;
        }

        return <<<PHP
    protected function tools(): array
    {
        return [
{$entries}
        ];
    }
PHP;
    }

    /** @param  array<string, mixed>  $binding */
    protected function toolBindingLine(array $binding): ?string
    {
        $ref = $binding['ref'] ?? '';

        if (str_starts_with($ref, 'toolkit:')) {
            $class = config('neuronai-studio.tools.'.Str::after($ref, 'toolkit:').'.class');

            return "            \\{$class}::make(),";
        }

        if (str_starts_with($ref, 'class:')) {
            $class = Str::after($ref, 'class:');

            return "            \\{$class}::make(),";
        }

        if (str_starts_with($ref, 'tool:db:')) {
            return '            // tool:db binding — export PHP class from studio tool first';
        }

        if (str_starts_with($ref, 'mcp:')) {
            return $this->mcpBindingLine(
                Str::after($ref, 'mcp:'),
                isset($binding['only']) ? implode(', ', $binding['only']) : null,
                $binding['exclude'] ?? [],
            );
        }

        return null;
    }

    /** @param  array<int, string>|null  $exclude */
    protected function mcpBindingLine(string $slug, ?string $onlyTools, ?array $exclude): string
    {
        $registry = app(McpRegistry::class);
        $config = var_export($registry->resolveConfig($slug), true);
        $line = "...McpConnector::make({$config})";

        if ($onlyTools !== null && trim($onlyTools) !== '') {
            $only = var_export(array_values(array_filter(array_map('trim', explode(',', $onlyTools)))), true);
            $line .= "->only({$only})";
        } elseif (! empty($exclude)) {
            $line .= '->exclude('.var_export(array_values($exclude), true).')';
        }

        return "            {$line}->tools(),";
    }
}
