<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Nodes;

use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\Events\GraphStepEvent;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\StructuredOutputValidationException;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\NodeExecutorRegistry;
use NeuronAI\Workflow\Events\StopEvent;
use NeuronAI\Workflow\Node;
use NeuronAI\Workflow\WorkflowState;

class GraphStepExecutorNode extends Node
{
    public function __construct(
        protected GraphContext $graphContext,
        protected ?NodeExecutorRegistry $executors = null,
    ) {
        $this->executors ??= app(NodeExecutorRegistry::class);
    }

    public function __invoke(GraphStepEvent $event, WorkflowState $state): GraphStepEvent|StopEvent
    {
        $nodeId = $event->nodeId;
        $nodeConfig = $this->graphContext->nodeConfig($nodeId);
        $nodeConfig['id'] = $nodeId;
        $nodeType = $nodeConfig['type'] ?? 'unknown';

        $startedAt = microtime(true);

        if ($state instanceof BuilderWorkflowState) {
            $state->emitStep('step_started', [
                'node_id' => $nodeId,
                'node_type' => $nodeType,
            ]);
        }

        if ($nodeType === 'stop') {
            if ($state instanceof BuilderWorkflowState) {
                $state->emitStep('step_completed', [
                    'node_id' => $nodeId,
                    'node_type' => $nodeType,
                    'handle' => 'default',
                    'duration_ms' => 0,
                ]);
            }

            return new StopEvent($state->all());
        }

        try {
            $handle = $this->executors->execute($nodeType, $nodeConfig, $state, $this->graphContext);
        } catch (StructuredOutputValidationException $exception) {
            $durationMs = (int) ((microtime(true) - $startedAt) * 1000);

            if ($state instanceof BuilderWorkflowState) {
                $state->emitStep('step_completed', [
                    'node_id' => $nodeId,
                    'node_type' => $nodeType,
                    'handle' => 'failed',
                    'duration_ms' => $durationMs,
                    'validation_errors' => $exception->validationErrors,
                    'failed' => true,
                ]);
            }

            throw $exception;
        }

        $durationMs = (int) ((microtime(true) - $startedAt) * 1000);

        $this->recordStep($state, $nodeId, $nodeType, $startedAt);

        if ($state instanceof BuilderWorkflowState) {
            $state->emitStep('step_completed', [
                'node_id' => $nodeId,
                'node_type' => $nodeType,
                'handle' => $handle,
                'duration_ms' => $durationMs,
            ]);
        }

        $nextNodeId = $this->graphContext->targetForHandle($nodeId, $handle);

        if (! $nextNodeId) {
            return new StopEvent($state->all());
        }

        $state->set('__current_node_id', $nextNodeId);

        return new GraphStepEvent($nextNodeId);
    }

    protected function recordStep(WorkflowState $state, string $nodeId, string $nodeType, float $startedAt): void
    {
        if (! $state instanceof BuilderWorkflowState) {
            return;
        }

        $steps = $state->get('__steps', []);
        $steps[] = [
            'node_id' => $nodeId,
            'node_type' => $nodeType,
            'state_snapshot' => $state->all(),
            'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
        ];
        $state->set('__steps', $steps);
    }
}
