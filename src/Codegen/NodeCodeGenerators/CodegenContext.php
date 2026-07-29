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

    public function providerExpression(string $provider, string $model, ?string $apiKeyOverride = null): string
    {
        $keyExpr = $this->providerKeyExpression($provider, $apiKeyOverride);
        $urlConfig = var_export("neuron.provider.{$provider}.url", true);

        return match ($provider) {
            'anthropic' => "(new \\NeuronAI\\Providers\\Anthropic\\Anthropic({$keyExpr}, ".var_export($model, true)."))",
            'openai' => "(new \\NeuronAI\\Providers\\OpenAI\\OpenAI({$keyExpr}, ".var_export($model, true)."))",
            'openai-responses' => "(new \\NeuronAI\\Providers\\OpenAI\\Responses\\OpenAIResponses({$keyExpr}, ".var_export($model, true)."))",
            'gemini' => "(new \\NeuronAI\\Providers\\Gemini\\Gemini({$keyExpr}, ".var_export($model, true)."))",
            'ollama' => "(new \\NeuronAI\\Providers\\Ollama\\Ollama((string) config({$urlConfig}, 'http://127.0.0.1:11434'), ".var_export($model, true)."))",
            'mistral' => "(new \\NeuronAI\\Providers\\Mistral\\Mistral({$keyExpr}, ".var_export($model, true)."))",
            'deepseek' => "(new \\NeuronAI\\Providers\\Deepseek\\Deepseek({$keyExpr}, ".var_export($model, true)."))",
            'huggingface' => "(new \\NeuronAI\\Providers\\HuggingFace\\HuggingFace({$keyExpr}, ".var_export($model, true)."))",
            'cohere' => "(new \\NeuronAI\\Providers\\Cohere\\Cohere({$keyExpr}, ".var_export($model, true)."))",
            default => throw new \InvalidArgumentException("Unsupported AI provider [{$provider}] for codegen."),
        };
    }

    protected function providerKeyExpression(string $provider, ?string $apiKeyOverride = null): string
    {
        if (is_string($apiKeyOverride) && $apiKeyOverride !== '') {
            if (str_starts_with($apiKeyOverride, 'var:')) {
                return '(string) app(\\DigitalElvis\\NeuronAIStudio\\Runtime\\ConfigValueResolver::class)->resolve('.var_export($apiKeyOverride, true).')';
            }

            // Literal override kept as resolve path only if env:/var:; otherwise avoid embedding secrets — fall back to config.
            if (str_starts_with($apiKeyOverride, 'env:') || preg_match('/^\{\{\s*env\./', $apiKeyOverride)) {
                return '(string) app(\\DigitalElvis\\NeuronAIStudio\\Runtime\\ConfigValueResolver::class)->resolve('.var_export($apiKeyOverride, true).')';
            }
        }

        $keyConfig = var_export("neuron.provider.{$provider}.key", true);

        return "(string) config({$keyConfig})";
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
