<?php

namespace DigitalElvis\NeuronAIStudio\Runtime;

use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\HumanNodeExecutor;

/**
 * Runs a single parallel branch subgraph in an isolated workflow state until it
 * reaches the join node. The interpreted runtime executes branches
 * sequentially (state isolation per branch), mirroring the NeuronAI
 * `ParallelEvent` semantics without requiring the Amp async executor.
 */
class ParallelBranchRunner
{
    /** @var list<string> */
    protected array $volatileKeys = [
        '__steps',
        '__current_node_id',
        '__loop_iterations',
        '__parallel_resume',
        '__parallel_results',
        HumanNodeExecutor::PASSTHROUGH_STATE_KEY,
    ];

    public function __construct(
        protected GraphExecutionLoop $loop,
    ) {}

    public function isolatedState(BuilderWorkflowState $parent, GraphContext $context): BuilderWorkflowState
    {
        $isolated = new BuilderWorkflowState($context, $parent->workflowRunId, $parent->all());
        $isolated->stepEmitter = $parent->stepEmitter;
        $isolated->set('__steps', []);
        $isolated->set('__parallel_resume', null);
        $isolated->set('__parallel_results', null);

        return $isolated;
    }

    /**
     * @param  array<string, mixed>|null  $baseline  State to diff against (defaults to the isolated state before running).
     * @return array{result: mixed, outputs: array<string, mixed>, steps: array<int, mixed>}
     */
    public function run(
        BuilderWorkflowState $isolated,
        string $entryNodeId,
        string $joinNodeId,
        GraphContext $context,
        ?array $baseline = null,
    ): array {
        $before = $this->filter($baseline ?? $isolated->all());

        $final = $this->loop->runFromNode($entryNodeId, $context, $isolated, $joinNodeId);

        $outputs = $this->diff($before, $this->filter($final->all()));

        return [
            'result' => $this->resolveResult($outputs),
            'outputs' => $outputs,
            'steps' => is_array($final->get('__steps')) ? $final->get('__steps') : [],
        ];
    }

    /**
     * The branch value: the single changed key when unambiguous, otherwise the
     * full map of keys the branch produced.
     *
     * @param  array<string, mixed>  $outputs
     */
    protected function resolveResult(array $outputs): mixed
    {
        if (count($outputs) === 1) {
            return reset($outputs);
        }

        return $outputs;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    protected function filter(array $state): array
    {
        foreach ($this->volatileKeys as $key) {
            unset($state[$key]);
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, mixed>
     */
    protected function diff(array $before, array $after): array
    {
        $changed = [];

        foreach ($after as $key => $value) {
            if (! array_key_exists($key, $before) || $before[$key] !== $value) {
                $changed[$key] = $value;
            }
        }

        return $changed;
    }
}
