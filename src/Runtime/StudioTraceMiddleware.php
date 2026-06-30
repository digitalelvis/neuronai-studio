<?php

namespace DigitalElvis\NeuronAIStudio\Runtime;

use Illuminate\Support\Str;
use NeuronAI\Workflow\Events\Event;
use NeuronAI\Workflow\Middleware\WorkflowMiddleware;
use NeuronAI\Workflow\NodeInterface;
use NeuronAI\Workflow\WorkflowState;
use ReflectionClass;

class StudioTraceMiddleware implements WorkflowMiddleware
{
    /** @var callable|null */
    public $stepEmitter = null;

    /** @var array<int, array{node_id: string, node_type: string, state_snapshot: array<string, mixed>, duration_ms: int}> */
    public array $steps = [];

    public function before(NodeInterface $node, Event $event, WorkflowState $state): void
    {
        $nodeId = $this->resolveNodeId($node);
        $nodeType = $this->resolveNodeType($node);

        $this->emit('step_started', [
            'node_id' => $nodeId,
            'node_type' => $nodeType,
        ]);

        $state->set('__studio_current_step', [
            'node_id' => $nodeId,
            'node_type' => $nodeType,
            'started_at' => microtime(true),
        ]);
    }

    public function after(NodeInterface $node, Event $result, WorkflowState $state): void
    {
        $current = $state->get('__studio_current_step', []);
        $nodeId = is_array($current) ? (string) ($current['node_id'] ?? $this->resolveNodeId($node)) : $this->resolveNodeId($node);
        $nodeType = is_array($current) ? (string) ($current['node_type'] ?? 'unknown') : 'unknown';
        $startedAt = is_array($current) ? (float) ($current['started_at'] ?? microtime(true)) : microtime(true);
        $durationMs = (int) ((microtime(true) - $startedAt) * 1000);

        $this->steps[] = [
            'node_id' => $nodeId,
            'node_type' => $nodeType,
            'state_snapshot' => $state->all(),
            'duration_ms' => $durationMs,
        ];

        $this->emit('step_completed', [
            'node_id' => $nodeId,
            'node_type' => $nodeType,
            'handle' => 'default',
            'duration_ms' => $durationMs,
        ]);
    }

    protected function resolveNodeId(NodeInterface $node): string
    {
        $reflection = new ReflectionClass($node);

        if ($reflection->hasConstant('STUDIO_NODE_ID')) {
            return (string) $reflection->getConstant('STUDIO_NODE_ID');
        }

        return $reflection->getShortName();
    }

    protected function resolveNodeType(NodeInterface $node): string
    {
        $id = $this->resolveNodeId($node);

        if (str_ends_with($id, 'Node')) {
            $id = substr($id, 0, -4);
        }

        return Str::snake($id);
    }

    /** @param  array<string, mixed>  $data */
    protected function emit(string $event, array $data): void
    {
        if ($this->stepEmitter !== null) {
            ($this->stepEmitter)($event, $data);
        }
    }
}
