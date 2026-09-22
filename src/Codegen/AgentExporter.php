<?php

namespace DigitalElvis\NeuronAIStudio\Codegen;

use DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators\CodegenContext;
use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\SkillDefinition;
use DigitalElvis\NeuronAIStudio\Registry\McpRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\Routing\RoutingConfig;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillParser;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillRepository;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class AgentExporter
{
    public function export(AgentDefinition $agent): array
    {
        CodegenGuard::ensureExport();

        $namespace = config('neuronai-studio.export_namespace', 'App\\Neuron');
        $path = config('neuronai-studio.export_path', app_path('Neuron'));
        $className = Str::studly($agent->slug).'Agent';
        $provider = (string) $agent->provider;
        $model = (string) ($agent->model ?: config("neuron.provider.{$provider}.model", 'gpt-4o-mini'));
        $context = new CodegenContext(new PhpArrayExporter);
        $routing = RoutingConfig::fromStored(is_array($agent->routing_config) ? $agent->routing_config : null);
        if ($routing !== null) {
            $providerExpression = $this->routingProviderExpression($routing, $context);
            $providerUse = $this->routingUseStatements($routing, $context);
        } else {
            $providerClass = $this->providerShortClass($provider);
            $providerExpression = $this->simplifyProviderExpression(
                $context->providerExpression($provider, $model, $agent->api_key)
            );
            $providerExpression = preg_replace(
                '/\\\\NeuronAI\\\\Providers\\\\(?:[^\\\\]+\\\\)*'.preg_quote($providerClass, '/').'\b/',
                $providerClass,
                $providerExpression,
                1
            ) ?? $providerExpression;
            $providerUse = $context->providerUseStatement($provider);
        }

        File::ensureDirectoryExists($path);

        $agent->loadMissing('mcpBindings');
        $skillExport = $this->exportSkillsSnapshot($agent, $path);
        $hasSkills = $skillExport['slugs'] !== [];
        $toolsMethod = $this->buildToolsMethod($agent, $hasSkills);

        $content = str_replace(
            [
                '{{ namespace }}',
                '{{ className }}',
                '{{ providerUse }}',
                '{{ providerExpression }}',
                '{{ instructionsMethod }}',
                '{{ toolsMethod }}',
                '{{ mcpUse }}',
                '{{ skillsUses }}',
            ],
            [
                $namespace,
                $className,
                $providerUse,
                $providerExpression,
                $this->buildInstructionsMethod($agent, $hasSkills, $skillExport['slugs']),
                $toolsMethod,
                $this->needsMcpConnector($agent) ? "use NeuronAI\\MCP\\McpConnector;\n" : '',
                $hasSkills ? $this->skillsUseStatements() : '',
            ],
            file_get_contents(__DIR__.'/Stubs/agent.stub')
        );

        $file = $path.'/'.$className.'.php';
        File::put($file, $content);

        return array_merge([$file], $skillExport['files']);
    }

    /**
     * @return array{files: list<string>, slugs: list<string>}
     */
    protected function exportSkillsSnapshot(AgentDefinition $agent, string $exportPath): array
    {
        $repository = app(SkillRepository::class);
        $parser = app(SkillParser::class);
        $files = [];
        $slugs = [];
        $skillsRoot = rtrim($exportPath, '/\\').'/skills';

        foreach ($agent->skills ?? [] as $binding) {
            if (! is_array($binding)) {
                continue;
            }

            $skill = $repository->findByRef((string) ($binding['ref'] ?? ''));

            if (! $skill instanceof SkillDefinition) {
                continue;
            }

            $skillDir = $skillsRoot.'/'.$skill->slug;
            File::ensureDirectoryExists($skillDir);

            $frontmatter = $parser->buildFrontmatter(array_filter([
                'name' => $skill->slug,
                'description' => $skill->description,
                'license' => $skill->license,
                'compatibility' => $skill->compatibility,
                'metadata' => $skill->metadata,
            ], fn ($value) => $value !== null && $value !== '' && $value !== []));

            $skillFile = $skillDir.'/SKILL.md';
            File::put($skillFile, trim($frontmatter."\n\n".(string) $skill->body));
            $files[] = $skillFile;

            foreach ($skill->files() as $relativePath => $contents) {
                $relativePath = ltrim(str_replace('\\', '/', (string) $relativePath), '/');
                $resourceFile = $skillDir.'/'.$relativePath;
                File::ensureDirectoryExists(dirname($resourceFile));

                if (str_starts_with($contents, 'base64:')) {
                    File::put($resourceFile, base64_decode(substr($contents, 7), true) ?: '');
                } else {
                    File::put($resourceFile, $contents);
                }

                $files[] = $resourceFile;
            }

            $slugs[] = $skill->slug;
        }

        return [
            'files' => $files,
            'slugs' => array_values(array_unique($slugs)),
        ];
    }

    protected function skillsUseStatements(): string
    {
        return implode("\n", [
            'use DigitalElvis\\NeuronAIStudio\\Codegen\\ExportedSkillLoader;',
            'use DigitalElvis\\NeuronAIStudio\\Runtime\\Skills\\SkillCatalog;',
            'use DigitalElvis\\NeuronAIStudio\\Runtime\\Skills\\SkillCatalogInjector;',
            'use DigitalElvis\\NeuronAIStudio\\Runtime\\Skills\\Tools\\ActivateSkillTool;',
            'use DigitalElvis\\NeuronAIStudio\\Runtime\\Skills\\Tools\\ReadSkillResourceTool;',
        ])."\n";
    }

    /** @param  list<string>  $slugs */
    protected function buildInstructionsMethod(AgentDefinition $agent, bool $hasSkills, array $slugs): string
    {
        $instructions = addslashes((string) $agent->instructions);

        if (! $hasSkills) {
            return <<<PHP
    protected function instructions(): string
    {
        return '{$instructions}';
    }
PHP;
        }

        $slugsExport = var_export($slugs, true);

        return <<<PHP
    protected function instructions(): string
    {
        \$base = '{$instructions}';

        return (new SkillCatalogInjector())->augment(\$base, \$this->skillCatalog());
    }

    protected function skillCatalog(): SkillCatalog
    {
        return ExportedSkillLoader::fromDirectory(__DIR__.'/../skills', {$slugsExport});
    }
PHP;
    }

    /**
     * CodegenContext wraps providers in parentheses for inline use; unwrap for a return statement.
     */
    protected function simplifyProviderExpression(string $expression): string
    {
        if (str_starts_with($expression, '(') && str_ends_with($expression, ')')) {
            return substr($expression, 1, -1);
        }

        return $expression;
    }

    protected function routingProviderExpression(RoutingConfig $routing, CodegenContext $context): string
    {
        $lines = ['RouterProvider::make()'];
        foreach ($routing->tiers as $name => $tier) {
            $inner = $this->simplifyProviderExpression(
                $context->providerExpression($tier['provider'], $tier['model'], $tier['api_key'])
            );
            $short = $this->providerShortClass($tier['provider']);
            $inner = preg_replace(
                '/\\\\NeuronAI\\\\Providers\\\\(?:[^\\\\]+\\\\)*'.preg_quote($short, '/').'\b/',
                $short,
                $inner,
                1
            ) ?? $inner;
            $lines[] = "            ->addProvider('{$name}', {$inner})";
        }

        $lines[] = "            ->setDefaultProvider('{$routing->defaultTier()}')";

        $key = $routing->classifierApiKey;
        $keyArg = 'null';
        if (is_string($key) && $key !== '' && (str_starts_with($key, 'var:') || str_starts_with($key, 'env:'))) {
            $keyArg = var_export($key, true);
        }

        $rule = '(new DifficultyRule(app(ClassifierRegistry::class)->resolve('.$keyArg.')))';
        if ($routing->has('easy')) {
            $rule .= "\n                ->easy('easy', maxScore: ".var_export($routing->easyMax, true).')';
        }
        if ($routing->has('medium')) {
            $rule .= "\n                ->medium('medium', maxScore: ".var_export($routing->mediumMax, true).')';
        }
        if ($routing->has('hard')) {
            $rule .= "\n                ->hard('hard')";
        }

        $lines[] = "            ->setRule(\n                {$rule}\n            )";

        return implode("\n", $lines);
    }

    protected function routingUseStatements(RoutingConfig $routing, CodegenContext $context): string
    {
        $uses = [
            "use DigitalElvis\\NeuronAIStudio\\Registry\\ClassifierRegistry;\n",
            "use NeuronAI\\Router\\RouterProvider;\n",
            "use NeuronAI\\Router\\Rules\\DifficultyRule;\n",
        ];
        $seen = [];
        foreach ($routing->tiers as $tier) {
            $provider = $tier['provider'];
            if (isset($seen[$provider])) {
                continue;
            }
            $seen[$provider] = true;
            $uses[] = $context->providerUseStatement($provider);
        }

        return implode('', $uses);
    }

    protected function providerShortClass(string $provider): string
    {
        return match ($provider) {
            'anthropic' => 'Anthropic',
            'openai' => 'OpenAI',
            'openai-responses' => 'OpenAIResponses',
            'gemini' => 'Gemini',
            'ollama' => 'Ollama',
            'mistral' => 'Mistral',
            'deepseek' => 'Deepseek',
            'huggingface' => 'HuggingFace',
            'cohere' => 'Cohere',
            default => throw new \InvalidArgumentException("Unsupported AI provider [{$provider}] for export."),
        };
    }

    protected function needsMcpConnector(AgentDefinition $agent): bool
    {
        if ($agent->mcpBindings->isNotEmpty()) {
            return true;
        }

        return collect($agent->tools ?? [])->contains(fn (array $binding) => str_starts_with($binding['ref'] ?? '', 'mcp:'));
    }

    protected function buildToolsMethod(AgentDefinition $agent, bool $hasSkills): string
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
            ->values();

        if ($entries->isEmpty() && ! $hasSkills) {
            return <<<'PHP'
    protected function tools(): array
    {
        return [];
    }
PHP;
        }

        $toolLines = $entries->implode("\n");

        if (! $hasSkills) {
            return <<<PHP
    protected function tools(): array
    {
        return [
{$toolLines}
        ];
    }
PHP;
        }

        $prefix = $toolLines === '' ? '' : "{$toolLines}\n";

        return <<<PHP
    protected function tools(): array
    {
        \$tools = [
{$prefix}        ];

        \$catalog = \$this->skillCatalog();

        if (! \$catalog->isEmpty()) {
            \$tools[] = new ActivateSkillTool(\$catalog);
            \$tools[] = new ReadSkillResourceTool(\$catalog);
        }

        return \$tools;
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
