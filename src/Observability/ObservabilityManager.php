<?php

namespace DigitalElvis\NeuronAIStudio\Observability;

use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioTrace;
use DigitalElvis\NeuronAIStudio\Runtime\TelemetryTracker;
use Inspector\Neuron\InspectorObserver;
use NeuronAI\Observability\ObserverInterface;
use Throwable;

class ObservabilityManager
{
    /**
     * @param  array{
     *     run?: StudioRun|null,
     *     trace?: StudioTrace|null,
     *     track_nodes?: bool,
     *     provider?: string|null,
     *     model?: string|null,
     *     parent_run?: StudioRun|null,
     *     session_id?: string|null,
     *     user_id?: string|null,
     * }  $meta
     */
    public function attach(object $target, array $meta = []): void
    {
        if (! method_exists($target, 'observe')) {
            return;
        }

        foreach ($this->resolveObservers($meta) as $observer) {
            $this->safeObserve($target, $observer);
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<ObserverInterface>
     */
    public function resolveObservers(array $meta = []): array
    {
        $observers = [];

        if ($this->isNativeTracingEnabled()) {
            $tracker = $this->makeNativeTracker($meta);
            if ($tracker !== null) {
                $observers[] = $tracker;
            }
        }

        if ($this->isInspectorActive()) {
            try {
                $observers[] = InspectorObserver::instance();
            } catch (Throwable $e) {
                $this->logWarning('Failed to create InspectorObserver: '.$e->getMessage());
            }
        }

        if ($this->isLangfuseActive()) {
            $langfuse = LangfuseNeuronObserverAdapter::make($this->langfuseContextFromMeta($meta));
            if ($langfuse !== null) {
                $observers[] = $langfuse;
            } else {
                LangfuseNeuronObserverAdapter::warnMissingPackageOnce();
            }
        }

        return $observers;
    }

    /**
     * Map Studio thread → Langfuse session; run → trace metadata.
     *
     * @param  array<string, mixed>  $meta
     * @return array{
     *     session_id?: string|null,
     *     user_id?: string|null,
     *     run_id?: string|null,
     *     studio_trace_id?: string|null,
     * }
     */
    protected function langfuseContextFromMeta(array $meta): array
    {
        $run = ($meta['run'] ?? null) instanceof StudioRun ? $meta['run'] : null;
        $trace = ($meta['trace'] ?? null) instanceof StudioTrace ? $meta['trace'] : null;

        $sessionId = $meta['session_id'] ?? null;
        if ((! is_string($sessionId) || $sessionId === '') && $run !== null) {
            $sessionId = is_string($run->thread_id) && $run->thread_id !== ''
                ? $run->thread_id
                : null;
        }

        $userId = $meta['user_id'] ?? null;
        if (! is_string($userId) || $userId === '') {
            $userId = null;
        }

        return array_filter([
            'session_id' => is_string($sessionId) && $sessionId !== '' ? $sessionId : null,
            'user_id' => $userId,
            'run_id' => $run !== null ? (string) $run->id : null,
            'studio_trace_id' => $trace !== null ? (string) $trace->id : null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    public function isNativeTracingEnabled(): bool
    {
        return (bool) config('neuronai-studio.observability.native_tracing', true);
    }

    public function isInspectorActive(): bool
    {
        $enabled = (bool) (
            config('neuronai-studio.observability.inspector.enabled')
            ?? config('neuronai-studio.inspector_enabled', true)
        );

        if (! $enabled) {
            return false;
        }

        $key = $_ENV['INSPECTOR_INGESTION_KEY']
            ?? getenv('INSPECTOR_INGESTION_KEY')
            ?: null;

        return is_string($key) && $key !== '';
    }

    public function isLangfuseActive(): bool
    {
        if (! (bool) config('neuronai-studio.observability.langfuse.enabled', true)) {
            return false;
        }

        $public = config('neuronai-studio.observability.langfuse.public_key');
        $secret = config('neuronai-studio.observability.langfuse.secret_key');

        return is_string($public) && $public !== ''
            && is_string($secret) && $secret !== '';
    }

    /**
     * Best-effort generation record for LLM calls outside the Agent EventBus
     * (e.g. LlmNodeExecutor direct provider chat/stream).
     *
     * @param  array{
     *     name?: string,
     *     model?: string|null,
     *     provider?: string|null,
     *     input?: mixed,
     *     output?: mixed,
     *     prompt_tokens?: int|null,
     *     completion_tokens?: int|null,
     * }  $payload
     */
    public function recordDirectLlmGeneration(array $payload): void
    {
        if (! $this->isLangfuseActive()) {
            return;
        }

        try {
            if (! class_exists('Axyr\\Langfuse\\LangfuseFacade')
                && ! class_exists('Axyr\\Langfuse\\Facades\\Langfuse')) {
                return;
            }

            $facade = class_exists('Axyr\\Langfuse\\Facades\\Langfuse')
                ? 'Axyr\\Langfuse\\Facades\\Langfuse'
                : 'Axyr\\Langfuse\\LangfuseFacade';

            if (! method_exists($facade, 'generation') && ! is_callable([$facade, 'generation'])) {
                // Prefer instance API when facade static helpers differ by version.
                if (app()->bound('langfuse')) {
                    $client = app('langfuse');
                    if (is_object($client) && method_exists($client, 'generation')) {
                        $client->generation($this->normalizeGenerationPayload($payload));
                    }
                }

                return;
            }

            $facade::generation($this->normalizeGenerationPayload($payload));
        } catch (Throwable $e) {
            $this->logWarning('Langfuse direct LLM generation failed: '.$e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function makeNativeTracker(array $meta): ?TelemetryTracker
    {
        $run = $meta['run'] ?? null;
        $trace = $meta['trace'] ?? null;

        if (! $run instanceof StudioRun || ! $trace instanceof StudioTrace) {
            return null;
        }

        $provider = isset($meta['provider']) && is_string($meta['provider']) && $meta['provider'] !== ''
            ? $meta['provider']
            : null;
        $model = isset($meta['model']) && is_string($meta['model']) && $meta['model'] !== ''
            ? $meta['model']
            : null;
        $parentRun = ($meta['parent_run'] ?? null) instanceof StudioRun
            ? $meta['parent_run']
            : null;
        $trackNodes = (bool) ($meta['track_nodes'] ?? true);

        return new TelemetryTracker($run, $trace, $trackNodes, $provider, $model, $parentRun);
    }

    protected function safeObserve(object $target, ObserverInterface $observer): void
    {
        try {
            $target->observe($observer);
        } catch (Throwable $e) {
            $this->logWarning('Observability observe failed ('.$observer::class.'): '.$e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeGenerationPayload(array $payload): array
    {
        $usage = [];
        if (isset($payload['prompt_tokens']) || isset($payload['completion_tokens'])) {
            $usage = array_filter([
                'input' => $payload['prompt_tokens'] ?? null,
                'output' => $payload['completion_tokens'] ?? null,
            ], fn ($v) => $v !== null);
        }

        return array_filter([
            'name' => $payload['name'] ?? 'llm-node',
            'model' => $payload['model'] ?? null,
            'input' => $payload['input'] ?? null,
            'output' => $payload['output'] ?? null,
            'metadata' => array_filter([
                'provider' => $payload['provider'] ?? null,
                ...(array) config('neuronai-studio.observability.metadata', []),
            ]),
            'usage' => $usage !== [] ? $usage : null,
        ], fn ($v) => $v !== null && $v !== []);
    }

    protected function logWarning(string $message): void
    {
        if (function_exists('logger')) {
            logger()->warning($message);
        }
    }
}
