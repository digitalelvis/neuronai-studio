<?php

namespace DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators;

use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\IntentClassifierNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\StructuredOutput\IntentClassificationResult;

class IntentClassifierNodeCodeGenerator implements NodeCodeGeneratorInterface
{
    public function supports(string $type): bool
    {
        return $type === 'intent_classifier';
    }

    public function generate(array $nodePlan, CodegenContext $context): array
    {
        $data = $nodePlan['data'];
        $provider = (string) ($data['provider'] ?? config('neuronai-studio.default_provider', 'openai'));
        $model = (string) ($data['model'] ?? config('neuronai-studio.default_model', 'gpt-4o-mini'));
        $message = var_export((string) ($data['message'] ?? '{{input}}'), true);
        $outputKey = var_export((string) ($data['output_key'] ?? 'intent'), true);
        $extraInstructions = var_export((string) ($data['instructions'] ?? ''), true);
        $vision = ($data['vision'] ?? false) === true ? 'true' : 'false';
        $memory = ($data['memory'] ?? false) === true ? 'true' : 'false';
        $intents = IntentClassifierNodeExecutor::normalizeIntents(
            is_array($data['intents'] ?? null) ? $data['intents'] : []
        );
        $intentsExport = $context->exporter->exportValue(array_values($intents), 2);
        $branchReturns = $nodePlan['branchReturns'] ?? [];
        $shortClass = class_basename(IntentClassificationResult::class);

        $branchBlocks = [];
        $intentIds = array_keys($intents);
        foreach ($intentIds as $index => $intentId) {
            $return = $context->returnStatement('', $intentId, $branchReturns);
            $condition = '$chosenId === '.var_export($intentId, true);
            if ($index === 0) {
                $branchBlocks[] = "        if ({$condition}) {\n            {$return}\n        }";
            } else {
                $branchBlocks[] = "        if ({$condition}) {\n            {$return}\n        }";
            }
        }

        $fallbackId = $intentIds[0] ?? 'other';
        $fallbackReturn = $context->returnStatement('', $fallbackId, $branchReturns);
        $branchBlocks[] = "        {$fallbackReturn}";
        $branchPhp = implode("\n\n", $branchBlocks);
        $apiKeyConfigLine = $this->apiKeyConfigLine($data);

        $body = <<<PHP
        \$intentsList = {$intentsExport};
        \$intents = IntentClassifierNodeExecutor::normalizeIntents(\$intentsList);
        if (count(\$intents) < 2) {
            throw new \\InvalidArgumentException('Intent Classifier requires at least two intents.');
        }

        \$template = {$message};
        \$prompt = {$context->interpolate('$template')};
        if (\$prompt === '' && \$state->has('input')) {
            \$prompt = (string) \$state->get('input');
        }

        \$attachments = app(MessageFactory::class)->resolveAttachmentsForNode(
            ['vision' => {$vision}],
            \$state,
            defaultVision: false,
        );
        \$userMessage = app(MessageFactory::class)->resolveMessageWithAttachments(\$prompt, \$attachments);

        \$threadId = \$state->get('__studio_thread_id');
        \$threadKey = is_string(\$threadId) && \$threadId !== '' ? \$threadId : null;
        \$nodeData = ['memory' => {$memory}, 'memory_config' => {$context->exporter->exportValue(
            is_array($data['memory_config'] ?? null) ? $data['memory_config'] : [],
            3
        )}];

        \$result = app(AgentRunner::class)->structuredInline([
            'provider' => {$this->exportConfigValue($provider)},
            'model' => {$this->exportConfigValue($model)},
            'instructions' => IntentClassifierNodeExecutor::buildInstructions(\$intents, {$extraInstructions}),
            'memory_config' => IntentClassifierNodeExecutor::resolveClassifierMemoryConfig({$memory}, \$nodeData),{$apiKeyConfigLine}
        ], \$userMessage, {$shortClass}::class, threadKey: \$threadKey);

        \$chosenId = IntentClassifierNodeExecutor::resolveIntentId(\$result->structured, \$intents);
        \$chosen = \$intents[\$chosenId];
        \$state->set({$outputKey}, \$chosenId);
        \$state->set({$outputKey}.'_name', \$chosen['name']);

{$branchPhp}
PHP;

        return [
            'body' => $body,
            'imports' => [
                'DigitalElvis\\NeuronAIStudio\\Runtime\\MessageFactory',
                'DigitalElvis\\NeuronAIStudio\\Runtime\\AgentRunner',
                'DigitalElvis\\NeuronAIStudio\\Runtime\\NodeExecutors\\IntentClassifierNodeExecutor',
                IntentClassificationResult::class,
            ],
        ];
    }

    protected function exportConfigValue(string $value): string
    {
        return var_export($value, true);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function apiKeyConfigLine(array $data): string
    {
        $apiKey = $data['api_key'] ?? null;
        if (! is_string($apiKey) || $apiKey === '') {
            return '';
        }

        return "\n            'api_key' => ".$this->exportConfigValue($apiKey).',';
    }
}
