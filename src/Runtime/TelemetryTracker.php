<?php

namespace DigitalElvis\NeuronAIStudio\Runtime;

use NeuronAI\Observability\ObserverInterface;
use NeuronAI\Observability\Events\InferenceStop;
use NeuronAI\Observability\Events\WorkflowNodeStart;
use NeuronAI\Observability\Events\WorkflowNodeEnd;
use NeuronAI\Observability\Events\ToolCalling;
use NeuronAI\Observability\Events\ToolCalled;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioTrace;
use DigitalElvis\NeuronAIStudio\Models\StudioTraceSpan;
use DigitalElvis\NeuronAIStudio\Usage\UsageRecorder;

class TelemetryTracker implements ObserverInterface
{
    protected array $spanStack = [];

    protected array $activeSpans = [];

    protected UsageRecorder $usageRecorder;

    public function __construct(
        protected StudioRun $run,
        protected StudioTrace $trace,
        protected bool $trackNodes = true,
        protected ?string $provider = null,
        protected ?string $model = null,
        protected ?StudioRun $parentRun = null,
        ?UsageRecorder $usageRecorder = null,
    ) {
        $this->usageRecorder = $usageRecorder ?? new UsageRecorder;
    }

    public function onEvent(string $event, object $source, mixed $data = null, ?string $branchId = null): void
    {
        $parentSpanId = end($this->spanStack) ?: null;

        if ($this->trackNodes && $event === 'workflow-node-start' && $data instanceof WorkflowNodeStart) {
            $name = $this->resolveNodeName($data->node);
            $span = StudioTraceSpan::create([
                'trace_id' => $this->trace->id,
                'parent_span_id' => $parentSpanId,
                'name' => $name,
                'type' => 'node',
                'status' => 'running',
                'started_at' => now(),
            ]);

            $this->activeSpans[spl_object_hash($source)] = $span;
            $this->spanStack[] = $span->id;
        }

        if ($this->trackNodes && $event === 'workflow-node-end' && $data instanceof WorkflowNodeEnd) {
            $hash = spl_object_hash($source);
            if (isset($this->activeSpans[$hash])) {
                /** @var StudioTraceSpan $span */
                $span = $this->activeSpans[$hash];
                $span->update([
                    'status' => 'completed',
                    'finished_at' => now(),
                    'duration_ms' => $span->started_at ? (int) $span->started_at->diffInMilliseconds(now()) : null,
                ]);
                unset($this->activeSpans[$hash]);
            }
            array_pop($this->spanStack);
        }

        if ($event === 'inference-stop' && $data instanceof InferenceStop) {
            $response = $data->response;
            $usage = $response->getUsage();

            $promptTokens = $usage ? $usage->inputTokens : 0;
            $completionTokens = $usage ? $usage->outputTokens : 0;

            $this->usageRecorder->recordLlmSpan(
                $this->run,
                $this->trace,
                $this->provider,
                $this->model,
                $promptTokens,
                $completionTokens,
                $this->parentRun,
                $parentSpanId ?: null,
                ['prompt' => $data->message ? $data->message->getContent() : null],
                ['response' => $response->getContent()],
            );
        }

        if ($event === 'tool-calling' && $data instanceof ToolCalling) {
            $span = StudioTraceSpan::create([
                'trace_id' => $this->trace->id,
                'parent_span_id' => $parentSpanId,
                'name' => $data->tool->getName(),
                'type' => 'tool',
                'status' => 'running',
                'started_at' => now(),
            ]);

            $this->activeSpans[spl_object_hash($data->tool)] = $span;
            $this->spanStack[] = $span->id;
        }

        if ($event === 'tool-called' && $data instanceof ToolCalled) {
            $hash = spl_object_hash($data->tool);
            if (isset($this->activeSpans[$hash])) {
                /** @var StudioTraceSpan $span */
                $span = $this->activeSpans[$hash];
                $span->update([
                    'status' => 'completed',
                    'finished_at' => now(),
                    'duration_ms' => $span->started_at ? (int) $span->started_at->diffInMilliseconds(now()) : null,
                ]);
                unset($this->activeSpans[$hash]);
            }
            array_pop($this->spanStack);
        }
    }

    protected function resolveNodeName(string $nodeClass): string
    {
        $parts = explode('\\', $nodeClass);

        return end($parts);
    }
}
