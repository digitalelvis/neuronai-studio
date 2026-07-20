<?php

namespace DigitalElvis\NeuronAIStudio\Observability;

use DateTimeImmutable;
use DateTimeZone;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Observability\Events\AgentError;
use NeuronAI\Observability\Events\InferenceStop;
use NeuronAI\Observability\Events\Retrieved;
use NeuronAI\Observability\Events\Retrieving;
use NeuronAI\Observability\Events\ToolCalled;
use NeuronAI\Observability\Events\ToolCalling;
use NeuronAI\Observability\Events\WorkflowEnd;
use NeuronAI\Observability\ObserverInterface;
use Throwable;

/**
 * Studio-owned Langfuse observer compatible with Neuron ObserverInterface
 * (including ?string $branchId).
 *
 * Intentionally does NOT load Axyr\Langfuse\NeuronAi\NeuronAiObserver — that
 * class still implements onEvent without $branchId and fatals on autoload
 * under Neuron AI 3.15+.
 */
class LangfuseNeuronObserverAdapter implements ObserverInterface
{
    protected static bool $missingPackageWarned = false;

    protected mixed $trace = null;

    /** @var array<string, float> */
    protected array $startTimes = [];

    /** @var array<string, mixed> */
    protected array $spans = [];

    /**
     * @param  array{
     *     session_id?: string|null,
     *     user_id?: string|null,
     *     run_id?: string|null,
     *     studio_trace_id?: string|null,
     * }  $context
     */
    public function __construct(
        protected object $langfuse,
        protected array $context = [],
    ) {}

    /**
     * @param  array{
     *     session_id?: string|null,
     *     user_id?: string|null,
     *     run_id?: string|null,
     *     studio_trace_id?: string|null,
     * }  $context
     */
    public static function make(array $context = []): ?self
    {
        $clientInterface = 'Axyr\\Langfuse\\Contracts\\LangfuseClientInterface';

        // Never touch NeuronAiObserver — class_exists() would fatal on autoload.
        if (! interface_exists($clientInterface)) {
            return null;
        }

        try {
            $client = app($clientInterface);
        } catch (Throwable) {
            return null;
        }

        if (! is_object($client)) {
            return null;
        }

        return new self($client, $context);
    }

    public static function warnMissingPackageOnce(): void
    {
        if (self::$missingPackageWarned) {
            return;
        }

        self::$missingPackageWarned = true;

        if (function_exists('logger')) {
            logger()->warning(
                'Langfuse observability is enabled but axyr/laravel-langfuse is not installed. '.
                'Run: composer require axyr/laravel-langfuse'
            );
        }
    }

    public static function resetWarnings(): void
    {
        self::$missingPackageWarned = false;
    }

    public function onEvent(string $event, object $source, mixed $data = null, ?string $branchId = null): void
    {
        try {
            if ($this->handleSimpleEvent($event, $source, $branchId)) {
                return;
            }

            match ($event) {
                'tool-called' => $this->toolCalled($data instanceof ToolCalled ? $data : null),
                'rag-retrieved' => $this->ragRetrieved($data instanceof Retrieved ? $data : null),
                'workflow-end' => $this->workflowEnd($source, $data instanceof WorkflowEnd ? $data : null, $branchId),
                'inference-stop' => $this->inferenceStop($source, $data instanceof InferenceStop ? $data : null, $branchId),
                'tool-calling' => $this->toolCalling($source, $data instanceof ToolCalling ? $data : null, $branchId),
                'rag-retrieving' => $this->ragRetrieving($source, $data instanceof Retrieving ? $data : null, $branchId),
                'error' => $this->handleError($source, $data instanceof AgentError ? $data : null, $branchId),
                default => null,
            };
        } catch (Throwable) {
            // Best-effort export — never break the run.
        }
    }

    protected function workflowEnd(object $source, ?WorkflowEnd $data, ?string $branchId): void
    {
        $trace = $this->getOrCreateTrace($source, $branchId);
        $traceBodyClass = 'Axyr\\Langfuse\\Dto\\TraceBody';

        if ($data !== null && class_exists($traceBodyClass) && method_exists($trace, 'update')) {
            $trace->update(new $traceBodyClass(
                output: $data->state->all(),
            ));
        }

        if (method_exists($this->langfuse, 'flush')) {
            $this->langfuse->flush();
        }
    }

    protected function inferenceStop(object $source, ?InferenceStop $data, ?string $branchId): void
    {
        $startTime = $this->startTimes['inference'] ?? microtime(true);
        $trace = $this->getOrCreateTrace($source, $branchId);
        $generationBodyClass = 'Axyr\\Langfuse\\Dto\\GenerationBody';

        if (! class_exists($generationBodyClass) || ! method_exists($trace, 'generation')) {
            return;
        }

        $generation = $trace->generation(new $generationBodyClass(
            name: 'inference',
            input: $this->extractInferenceInput($data),
            startTime: $this->formatTime($startTime),
            metadata: $this->branchMetadata($branchId),
        ));

        if (is_object($generation) && method_exists($generation, 'end')) {
            $generation->end(
                endTime: $this->formatTime(microtime(true)),
                output: $data?->response->getContent(),
                usage: $this->extractInferenceUsage($data),
            );
        }

        unset($this->startTimes['inference']);
    }

    protected function toolCalling(object $source, ?ToolCalling $data, ?string $branchId): void
    {
        if ($data === null) {
            return;
        }

        $toolName = $data->tool->getName();
        $this->startTimes["tool-{$toolName}"] = microtime(true);

        $trace = $this->getOrCreateTrace($source, $branchId);
        $spanBodyClass = 'Axyr\\Langfuse\\Dto\\SpanBody';

        if (! class_exists($spanBodyClass) || ! method_exists($trace, 'span')) {
            return;
        }

        $this->spans["tool-{$toolName}"] = $trace->span(new $spanBodyClass(
            name: "tool-{$toolName}",
            startTime: $this->formatTime($this->startTimes["tool-{$toolName}"]),
            metadata: $this->branchMetadata($branchId),
        ));
    }

    protected function toolCalled(?ToolCalled $data): void
    {
        if ($data === null) {
            return;
        }

        $toolName = $data->tool->getName();
        $span = $this->spans["tool-{$toolName}"] ?? null;

        if (! is_object($span) || ! method_exists($span, 'end')) {
            return;
        }

        $result = null;

        try {
            $result = $data->tool->getResult();
        } catch (Throwable) {
        }

        $span->end(
            endTime: $this->formatTime(microtime(true)),
            output: $result,
        );

        unset($this->spans["tool-{$toolName}"], $this->startTimes["tool-{$toolName}"]);
    }

    protected function ragRetrieving(object $source, ?Retrieving $data, ?string $branchId): void
    {
        if ($data === null) {
            return;
        }

        $this->startTimes['rag'] = microtime(true);

        $trace = $this->getOrCreateTrace($source, $branchId);
        $spanBodyClass = 'Axyr\\Langfuse\\Dto\\SpanBody';

        if (! class_exists($spanBodyClass) || ! method_exists($trace, 'span')) {
            return;
        }

        $this->spans['rag'] = $trace->span(new $spanBodyClass(
            name: 'rag-retrieval',
            startTime: $this->formatTime($this->startTimes['rag']),
            input: $data->question->getContent(),
            metadata: $this->branchMetadata($branchId),
        ));
    }

    protected function ragRetrieved(?Retrieved $data): void
    {
        $span = $this->spans['rag'] ?? null;

        if (! is_object($span) || ! method_exists($span, 'end')) {
            return;
        }

        $output = null;

        if ($data !== null) {
            $output = [
                'question' => $data->question->getContent(),
                'documents' => count($data->documents),
            ];
        }

        $span->end(
            endTime: $this->formatTime(microtime(true)),
            output: $output,
        );

        unset($this->spans['rag'], $this->startTimes['rag']);
    }

    protected function handleError(object $source, ?AgentError $data, ?string $branchId): void
    {
        if ($data === null) {
            return;
        }

        $trace = $this->getOrCreateTrace($source, $branchId);
        $traceBodyClass = 'Axyr\\Langfuse\\Dto\\TraceBody';

        if (! class_exists($traceBodyClass) || ! method_exists($trace, 'update')) {
            return;
        }

        $trace->update(new $traceBodyClass(
            metadata: array_filter([
                'error' => $data->exception->getMessage(),
                'error_trace' => $data->exception->getTraceAsString(),
                'branchId' => $branchId,
            ], fn ($v) => $v !== null && $v !== ''),
        ));
    }

    protected function handleSimpleEvent(string $event, object $source, ?string $branchId): bool
    {
        if ($event === 'workflow-start') {
            $this->getOrCreateTrace($source, $branchId);

            return true;
        }

        if ($event === 'inference-start') {
            $this->startTimes['inference'] = microtime(true);

            return true;
        }

        return false;
    }

    protected function extractInferenceInput(?InferenceStop $data): mixed
    {
        if ($data !== null && $data->message instanceof Message) {
            return $data->message->getContent();
        }

        return null;
    }

    protected function extractInferenceUsage(?InferenceStop $data): mixed
    {
        $neuronUsage = $data?->response->getUsage();
        $usageClass = 'Axyr\\Langfuse\\Dto\\Usage';

        if ($neuronUsage === null || ! class_exists($usageClass)) {
            return null;
        }

        return new $usageClass(
            input: $neuronUsage->inputTokens,
            output: $neuronUsage->outputTokens,
            total: $neuronUsage->getTotal(),
        );
    }

    protected function getOrCreateTrace(object $source, ?string $branchId): object
    {
        if (is_object($this->trace)) {
            return $this->trace;
        }

        // Studio run context → always open a new Langfuse trace (session groups runs).
        // Without Studio context, reuse a sticky client currentTrace when present.
        if (! $this->hasStudioContext() && method_exists($this->langfuse, 'currentTrace')) {
            $nullTraceClass = 'Axyr\\Langfuse\\Objects\\NullLangfuseTrace';
            $existing = $this->langfuse->currentTrace();
            $isNullTrace = is_object($existing)
                && class_exists($nullTraceClass)
                && $existing instanceof $nullTraceClass;

            if (is_object($existing) && ! $isNullTrace) {
                $this->trace = $existing;

                return $existing;
            }
        }

        $traceBodyClass = 'Axyr\\Langfuse\\Dto\\TraceBody';
        $agentName = $this->getShortClassName($source);

        if (! class_exists($traceBodyClass) || ! method_exists($this->langfuse, 'trace')) {
            $this->trace = new \stdClass;

            return $this->trace;
        }

        $trace = $this->langfuse->trace(new $traceBodyClass(
            name: "neuron-ai-{$agentName}",
            sessionId: $this->sessionId(),
            userId: $this->userId(),
            metadata: array_filter([
                'source' => 'neuronai-studio',
                'thread_id' => $this->sessionId(),
                'run_id' => $this->contextString('run_id'),
                'studio_trace_id' => $this->contextString('studio_trace_id'),
                'branchId' => $branchId,
            ], fn ($v) => $v !== null && $v !== ''),
        ));

        if (is_object($trace) && method_exists($this->langfuse, 'setCurrentTrace')) {
            $this->langfuse->setCurrentTrace($trace);
        }

        $this->trace = is_object($trace) ? $trace : new \stdClass;

        return $this->trace;
    }

    protected function hasStudioContext(): bool
    {
        return $this->sessionId() !== null || $this->contextString('run_id') !== null;
    }

    protected function sessionId(): ?string
    {
        return $this->contextString('session_id');
    }

    protected function userId(): ?string
    {
        return $this->contextString('user_id');
    }

    protected function contextString(string $key): ?string
    {
        $value = $this->context[$key] ?? null;

        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    /**
     * @return array<string, string>|null
     */
    protected function branchMetadata(?string $branchId): ?array
    {
        if ($branchId === null || $branchId === '') {
            return null;
        }

        return ['branchId' => $branchId];
    }

    protected function getShortClassName(object $object): string
    {
        $parts = explode('\\', $object::class);

        return end($parts);
    }

    protected function formatTime(float $microtime): string
    {
        $dt = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6f', $microtime));

        if ($dt === false) {
            return now()->toIso8601ZuluString();
        }

        return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
    }
}
