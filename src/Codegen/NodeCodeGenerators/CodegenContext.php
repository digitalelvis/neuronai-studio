<?php

namespace DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators;

use DigitalElvis\NeuronAIStudio\Codegen\PhpArrayExporter;

class CodegenContext
{
    public function __construct(
        public PhpArrayExporter $exporter,
    ) {}

    public function interpolate(string $templateVar): string
    {
        return "preg_replace_callback('/\\{\\{(\\w+)\\}\\}/', fn (array \$m) => is_array(\$state->get(\$m[1])) ? json_encode(\$state->get(\$m[1]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '' : (string) (\$state->get(\$m[1]) ?? ''), {$templateVar}) ?? {$templateVar}";
    }

    public function providerExpression(string $provider, string $model): string
    {
        $keyConfig = var_export("neuron.provider.{$provider}.key", true);
        $urlConfig = var_export("neuron.provider.{$provider}.url", true);

        return match ($provider) {
            'anthropic' => "(new \\NeuronAI\\Providers\\Anthropic\\Anthropic((string) config({$keyConfig}), ".var_export($model, true)."))",
            'openai' => "(new \\NeuronAI\\Providers\\OpenAI\\OpenAI((string) config({$keyConfig}), ".var_export($model, true)."))",
            'openai-responses' => "(new \\NeuronAI\\Providers\\OpenAI\\Responses\\OpenAIResponses((string) config({$keyConfig}), ".var_export($model, true)."))",
            'gemini' => "(new \\NeuronAI\\Providers\\Gemini\\Gemini((string) config({$keyConfig}), ".var_export($model, true)."))",
            'ollama' => "(new \\NeuronAI\\Providers\\Ollama\\Ollama((string) config({$urlConfig}, 'http://127.0.0.1:11434'), ".var_export($model, true)."))",
            'mistral' => "(new \\NeuronAI\\Providers\\Mistral\\Mistral((string) config({$keyConfig}), ".var_export($model, true)."))",
            'deepseek' => "(new \\NeuronAI\\Providers\\Deepseek\\Deepseek((string) config({$keyConfig}), ".var_export($model, true)."))",
            'huggingface' => "(new \\NeuronAI\\Providers\\HuggingFace\\HuggingFace((string) config({$keyConfig}), ".var_export($model, true)."))",
            'cohere' => "(new \\NeuronAI\\Providers\\Cohere\\Cohere((string) config({$keyConfig}), ".var_export($model, true)."))",
            default => throw new \InvalidArgumentException("Unsupported AI provider [{$provider}] for codegen."),
        };
    }

    public function providerUseStatement(string $provider): string
    {
        return match ($provider) {
            'anthropic' => "use NeuronAI\\Providers\\Anthropic\\Anthropic;\n",
            'openai' => "use NeuronAI\\Providers\\OpenAI\\OpenAI;\n",
            'openai-responses' => "use NeuronAI\\Providers\\OpenAI\\Responses\\OpenAIResponses;\n",
            'gemini' => "use NeuronAI\\Providers\\Gemini\\Gemini;\n",
            'ollama' => "use NeuronAI\\Providers\\Ollama\\Ollama;\n",
            'mistral' => "use NeuronAI\\Providers\\Mistral\\Mistral;\n",
            'deepseek' => "use NeuronAI\\Providers\\Deepseek\\Deepseek;\n",
            'huggingface' => "use NeuronAI\\Providers\\HuggingFace\\HuggingFace;\n",
            'cohere' => "use NeuronAI\\Providers\\Cohere\\Cohere;\n",
            default => throw new \InvalidArgumentException("Unsupported AI provider [{$provider}] for codegen."),
        };
    }

    public function returnStatement(string $returnType, ?string $branchHandle = null, array $branchReturns = []): string
    {
        if ($branchHandle !== null && isset($branchReturns[$branchHandle])) {
            $event = $branchReturns[$branchHandle];

            return "return new {$event}();";
        }

        return "return new {$returnType}();";
    }
}
