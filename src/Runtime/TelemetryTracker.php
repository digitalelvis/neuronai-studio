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

class TelemetryTracker implements ObserverInterface
{
    protected array $spanStack = [];
    protected array $activeSpans = [];

    public function __construct(
        protected StudioRun $run,
        protected StudioTrace $trace,
        protected bool $trackNodes = true
    ) {}

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
            $totalTokens = $usage ? $usage->getTotal() : 0;

            StudioTraceSpan::create([
                'trace_id' => $this->trace->id,
                'parent_span_id' => $parentSpanId,
                'name' => 'llm_inference',
                'type' => 'llm',
                'status' => 'completed',
                'input' => ['prompt' => $data->message ? $data->message->getContent() : null],
                'output' => ['response' => $response->getContent()],
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $totalTokens,
                'started_at' => now(),
                'finished_at' => now(),
                'duration_ms' => 0,
            ]);

            // Accumulate tokens to the run in real-time
            $this->run->increment('prompt_tokens', $promptTokens);
            $this->run->increment('completion_tokens', $completionTokens);
            $this->run->increment('total_tokens', $totalTokens);
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
