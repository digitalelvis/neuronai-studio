<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors;

use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\HumanInputRequiredException;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\ParallelBranchInterruptException;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\Parallel\ConcurrentBranchScheduler;
use DigitalElvis\NeuronAIStudio\Runtime\Parallel\SerializingEmitter;
use DigitalElvis\NeuronAIStudio\Runtime\ParallelBranchRunner;
use NeuronAI\Workflow\WorkflowState;
use RuntimeException;

/**
 * Spawns the branch subgraphs attached to a fork node, runs each one in an
 * isolated state up to the paired join node, and collects their results.
 *
 * With `parallel.concurrency=concurrent` (default) and Amp available, pending
 * branches run as concurrent Amp fibers; otherwise they run sequentially.
 *
 * On resume after a branch interrupt, already-completed branches are reused
 * from the checkpoint, the interrupted branch continues from its pending node
 * with the injected response, and branches that had not started yet run fresh.
 */
class ForkNodeExecutor implements NodeExecutorInterface
{
    public function __construct(
        protected ParallelBranchRunner $runner,
        protected ConcurrentBranchScheduler $scheduler = new ConcurrentBranchScheduler,
    ) {}

    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        if (! $state instanceof BuilderWorkflowState) {
            throw new RuntimeException('Parallel execution requires the interpreted BuilderWorkflowState.');
        }

        $forkId = (string) ($nodeConfig['id'] ?? 'fork');
        $joinId = $this->resolveJoinId($forkId, $nodeConfig, $context);
        $branches = $this->resolveBranches($forkId, $context);

        $resume = $state->get('__parallel_resume');
        $resume = is_array($resume) && ($resume['fork_id'] ?? null) === $forkId ? $resume : null;
        $state->set('__parallel_resume', null);

        $results = $resume !== null && is_array($resume['completed'] ?? null) ? $resume['completed'] : [];
        $outputs = $resume !== null && is_array($resume['completed_outputs'] ?? null) ? $resume['completed_outputs'] : [];
        $pending = $resume !== null && is_array($resume['pending'] ?? null) ? $resume['pending'] : null;

        (new SerializingEmitter($state->stepEmitter))->wrapStateEmitter($state);

        $pendingCallables = [];

        foreach ($branches as $branchId => $entryNodeId) {
            if (array_key_exists($branchId, $results)) {
                continue;
            }

            if ($pending !== null && (string) ($pending['branch_id'] ?? '') === (string) $branchId) {
                $outputKey = (string) ($pending['output_key'] ?? 'human_response');
                $pendingState = is_array($pending['state'] ?? null) ? $pending['state'] : [];
                $seededState = array_merge($pendingState, [$outputKey => $pending['response'] ?? null]);
                $entry = (string) ($pending['node_id'] ?? $entryNodeId);

                $pendingCallables[$branchId] = function () use (
                    $forkId,
                    $joinId,
                    $branchId,
                    $entry,
                    $state,
                    $context,
                    $seededState,
                    $pendingState,
                ): array {
                    return $this->runBranchOutcome(
                        $forkId,
                        $joinId,
                        (string) $branchId,
                        $entry,
                        $state,
                        $context,
                        $seededState,
                        $pendingState,
                    );
                };

                continue;
            }

            $pendingCallables[$branchId] = function () use (
                $forkId,
                $joinId,
                $branchId,
                $entryNodeId,
                $state,
                $context,
            ): array {
                return $this->runBranchOutcome(
                    $forkId,
                    $joinId,
                    (string) $branchId,
                    $entryNodeId,
                    $state,
                    $context,
                    null,
                    null,
                );
            };
        }

        [$results, $outputs] = $this->scheduler->run($pendingCallables, $results, $outputs);
        $results = $this->orderByBranchKeys($branches, $results);

        $this->storeResults($state, $forkId, $joinId, $results, $outputs);

        return 'default';
    }

    /**
     * @param  array<string, string>  $branches
     * @param  array<string, mixed>  $results
     * @return array<string, mixed>
     */
    protected function orderByBranchKeys(array $branches, array $results): array
    {
        $ordered = [];

        foreach (array_keys($branches) as $branchId) {
            if (array_key_exists($branchId, $results)) {
                $ordered[$branchId] = $results[$branchId];
            }
        }

        foreach ($results as $branchId => $value) {
            if (! array_key_exists($branchId, $ordered)) {
                $ordered[$branchId] = $value;
            }
        }

        return $ordered;
    }

    /**
     * @param  array<string, mixed>|null  $seedState
     * @param  array<string, mixed>|null  $baseline
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    protected function runBranchOutcome(
        string $forkId,
        string $joinId,
        string $branchId,
        string $entryNodeId,
        BuilderWorkflowState $state,
        GraphContext $context,
        ?array $seedState,
        ?array $baseline,
    ): array {
        [$partialResults, $partialOutputs] = $this->runBranch(
            $forkId,
            $joinId,
            $branchId,
            $entryNodeId,
            $state,
            $context,
            [],
            [],
            $seedState,
            $baseline,
        );

        return [$partialResults, $partialOutputs];
    }

    /**
     * @param  array<string, mixed>  $results
     * @param  array<string, mixed>  $outputs
     * @param  array<string, mixed>|null  $seedState  Pre-populated branch state (resume path).
     * @param  array<string, mixed>|null  $baseline  Diff baseline (resume path, excludes the seeded response).
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    protected function runBranch(
        string $forkId,
        string $joinId,
        string $branchId,
        string $entryNodeId,
        BuilderWorkflowState $state,
        GraphContext $context,
        array $results,
        array $outputs,
        ?array $seedState,
        ?array $baseline,
    ): array {
        $state->emitStep('branch_started', [
            'fork_id' => $forkId,
            'branch_id' => $branchId,
        ]);

        $isolated = $this->runner->isolatedState($state, $context);

        if ($seedState !== null) {
            foreach ($seedState as $key => $value) {
                $isolated->set($key, $value);
            }
        }

        $startedAt = microtime(true);

        try {
            $branch = $this->runner->run($isolated, $entryNodeId, $joinId, $context, $baseline);
        } catch (HumanInputRequiredException $exception) {
            throw new ParallelBranchInterruptException(
                $forkId,
                $joinId,
                $branchId,
                $exception->nodeId,
                $exception->outputKey,
                $exception->prompt,
                'human',
                $this->snapshot($isolated),
                $results,
                $outputs,
            );
        }

        $results[$branchId] = $branch['result'];
        $outputs = array_merge($outputs, $branch['outputs']);
        $this->mergeSteps($state, $branch['steps']);

        $state->emitStep('branch_completed', [
            'fork_id' => $forkId,
            'branch_id' => $branchId,
            'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
        ]);

        return [$results, $outputs];
    }

    /**
     * @param  array<string, mixed>  $results
     * @param  array<string, mixed>  $outputs
     */
    protected function storeResults(
        BuilderWorkflowState $state,
        string $forkId,
        string $joinId,
        array $results,
        array $outputs,
    ): void {
        foreach ($outputs as $key => $value) {
            $state->set($key, $value);
        }

        $state->set('__parallel_results', [
            'fork_id' => $forkId,
            'join_id' => $joinId,
            'results' => $results,
        ]);
    }

    protected function resolveJoinId(string $forkId, array $nodeConfig, GraphContext $context): string
    {
        $joinId = $context->targetForHandle($forkId, 'default')
            ?? (($nodeConfig['data']['join'] ?? null) ?: null);

        if (! is_string($joinId) || $joinId === '') {
            throw new RuntimeException("Fork node \"{$forkId}\" has no paired join node.");
        }

        return $joinId;
    }

    /**
     * @return array<string, string>  Map of branchId (source handle) => entry node id.
     */
    protected function resolveBranches(string $forkId, GraphContext $context): array
    {
        $branches = [];

        foreach ($context->outgoingEdges($forkId) as $edge) {
            $handle = (string) ($edge['sourceHandle'] ?? 'default');
            $target = $edge['target'] ?? null;

            if ($handle === 'default' || ! is_string($target) || $target === '') {
                continue;
            }

            $branches[$handle] = $target;
        }

        if ($branches === []) {
            throw new RuntimeException("Fork node \"{$forkId}\" has no branch edges.");
        }

        return $branches;
    }

    /**
     * @param  array<int, mixed>  $steps
     */
    protected function mergeSteps(BuilderWorkflowState $state, array $steps): void
    {
        if ($steps === []) {
            return;
        }

        $existing = is_array($state->get('__steps')) ? $state->get('__steps') : [];

        foreach ($steps as $step) {
            $existing[] = $step;
        }

        $state->set('__steps', $existing);
    }

    /**
     * @return array<string, mixed>
     */
    protected function snapshot(BuilderWorkflowState $isolated): array
    {
        $data = $isolated->all();

        foreach (['__steps', '__current_node_id', '__loop_iterations', '__parallel_resume', '__parallel_results'] as $key) {
            unset($data[$key]);
        }

        return $data;
    }
}
