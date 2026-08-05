<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors;

use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\StateTemplateInterpolator;
use DigitalElvis\NeuronAIStudio\Runtime\StructuredOutput\IntentClassificationResult;
use InvalidArgumentException;
use NeuronAI\Workflow\WorkflowState;

class IntentClassifierNodeExecutor implements NodeExecutorInterface
{
    public function __construct(
        protected AgentRunner $agentRunner,
        protected MessageFactory $messages,
    ) {}

    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        $data = $nodeConfig['data'] ?? [];
        $intents = self::normalizeIntents(is_array($data['intents'] ?? null) ? $data['intents'] : []);

        if (count($intents) < 2) {
            throw new InvalidArgumentException('Intent Classifier requires at least two intents.');
        }

        $provider = $data['provider'] ?? config('neuronai-studio.default_provider');
        $model = $data['model'] ?? config('neuronai-studio.default_model');
        $outputKey = (string) ($data['output_key'] ?? 'intent');
        $rawMessage = array_key_exists('message', $data)
            ? (string) $data['message']
            : (string) $state->get('input', '');

        if ($rawMessage === '') {
            $rawMessage = (string) $state->get('input', '');
        }

        $message = StateTemplateInterpolator::interpolate($rawMessage, $state);
        $attachments = $this->messages->resolveAttachmentsForNode($data, $state, defaultVision: false);
        $userMessage = $this->messages->resolveMessageWithAttachments($message, $attachments);

        // Always nest under the workflow conversation thread for metering (like LLM).
        // Memory only controls whether chat history is loaded (eloquent vs in-memory).
        $threadId = $state->get('__studio_thread_id');
        $threadKey = is_string($threadId) && $threadId !== '' ? $threadId : null;

        $memoryEnabled = ($data['memory'] ?? false) === true;
        $config = [
            'provider' => $provider,
            'model' => $model,
            'instructions' => self::buildInstructions($intents, (string) ($data['instructions'] ?? '')),
            'memory_config' => self::resolveClassifierMemoryConfig($memoryEnabled, $data),
        ];

        $apiKey = $data['api_key'] ?? null;
        if (is_string($apiKey) && $apiKey !== '') {
            $config['api_key'] = $apiKey;
        }

        $parentRun = $this->resolveParentRun($state);

        $result = $this->agentRunner->structuredInline(
            $config,
            $userMessage,
            IntentClassificationResult::class,
            threadKey: $threadKey,
            parentRun: $parentRun,
        );

        $chosenId = self::resolveIntentId($result->structured, $intents);
        $chosen = $intents[$chosenId];

        $state->set($outputKey, $chosenId);
        $state->set($outputKey.'_name', $chosen['name']);
        $this->captureRunUsage($state, $result->runId);

        return $chosenId;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function resolveClassifierMemoryConfig(bool $memoryEnabled, array $data): array
    {
        if (! $memoryEnabled) {
            return ['driver' => 'in_memory'];
        }

        $override = isset($data['memory_config']) && is_array($data['memory_config'])
            ? $data['memory_config']
            : [];

        // Explicit eloquent (or inherit) so history loads from the workflow thread.
        if (! isset($override['driver']) || $override['driver'] === null || $override['driver'] === '') {
            $override['driver'] = 'eloquent';
        }

        return $override;
    }

    /**
     * @param  array<int, mixed>  $raw
     * @return array<string, array{id: string, name: string, description: string}>
     */
    public static function normalizeIntents(array $raw): array
    {
        $normalized = [];

        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }

            $id = trim((string) ($item['id'] ?? ''));
            if ($id === '' || ! preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $id)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? $id));
            if ($name === '') {
                $name = $id;
            }

            $normalized[$id] = [
                'id' => $id,
                'name' => $name,
                'description' => trim((string) ($item['description'] ?? '')),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<string, array{id: string, name: string, description: string}>  $intents
     */
    public static function buildInstructions(array $intents, string $extra = ''): string
    {
        $lines = [
            'You are an intent classifier. Classify the user message into exactly one of the allowed intents.',
            'Set intent_id to one of the allowed ids below. Do not invent new ids.',
            '',
            'Allowed intents:',
        ];

        foreach ($intents as $intent) {
            $desc = $intent['description'] !== '' ? $intent['description'] : $intent['name'];
            $lines[] = "- {$intent['id']}: {$desc}";
        }

        $extra = trim($extra);
        if ($extra !== '') {
            $lines[] = '';
            $lines[] = 'Additional instructions:';
            $lines[] = $extra;
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>|null  $structured
     * @param  array<string, array{id: string, name: string, description: string}>  $intents
     */
    public static function resolveIntentId(?array $structured, array $intents): string
    {
        $raw = is_array($structured) ? (string) ($structured['intent_id'] ?? '') : '';
        $raw = trim($raw);

        if ($raw !== '' && isset($intents[$raw])) {
            return $raw;
        }

        if (isset($intents['other'])) {
            return 'other';
        }

        if (isset($intents['unknown'])) {
            return 'unknown';
        }

        return array_key_first($intents) ?? 'other';
    }

    protected function resolveParentRun(WorkflowState $state): ?StudioRun
    {
        $runId = $state->get('__studio_run_id');
        if (! is_string($runId) || $runId === '') {
            return null;
        }

        return StudioRun::query()->find($runId);
    }

    protected function captureRunUsage(WorkflowState $state, ?string $runId): void
    {
        if ($runId === null) {
            return;
        }

        $run = StudioRun::query()->find($runId);
        if ($run === null) {
            return;
        }

        $state->set('__step_usage', [
            'prompt_tokens' => $run->prompt_tokens ?? 0,
            'completion_tokens' => $run->completion_tokens ?? 0,
            'total_tokens' => $run->total_tokens ?? 0,
            'estimated_cost' => $run->estimated_cost ?? '0.000000',
            'currency' => config('neuronai-studio.usage.currency', 'USD'),
        ]);
    }
}
