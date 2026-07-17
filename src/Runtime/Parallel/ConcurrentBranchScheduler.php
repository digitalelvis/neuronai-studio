<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Parallel;

use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\ParallelBranchInterruptException;
use Throwable;

use function Amp\async;

/**
 * Runs branch callables concurrently via Amp fibers when enabled, otherwise sequentially.
 *
 * @phpstan-type BranchCallable callable(): array{0: array<string, mixed>, 1: array<string, mixed>}
 */
class ConcurrentBranchScheduler
{
    public function shouldRunConcurrent(int $branchCount): bool
    {
        if ($branchCount <= 1) {
            return false;
        }

        $mode = (string) config('neuronai-studio.parallel.concurrency', 'concurrent');

        if ($mode !== 'concurrent') {
            return false;
        }

        return function_exists('Amp\\async');
    }

    /**
     * @param  array<string, callable(): array{0: array<string, mixed>, 1: array<string, mixed>}>  $branches
     * @param  array<string, mixed>  $seedResults
     * @param  array<string, mixed>  $seedOutputs
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public function run(array $branches, array $seedResults = [], array $seedOutputs = []): array
    {
        $results = $seedResults;
        $outputs = $seedOutputs;

        if ($branches === []) {
            return [$results, $outputs];
        }

        if (! $this->shouldRunConcurrent(count($branches))) {
            foreach ($branches as $branchId => $callable) {
                [$branchResults, $branchOutputs] = $callable();
                $results = array_merge($results, $branchResults);
                $outputs = array_merge($outputs, $branchOutputs);
            }

            return [$results, $outputs];
        }

        $futures = [];
        foreach ($branches as $branchId => $callable) {
            $futures[$branchId] = async($callable);
        }

        $interrupt = null;
        $collectedResults = [];
        $collectedOutputs = [];

        foreach ($futures as $branchId => $future) {
            try {
                [$branchResults, $branchOutputs] = $future->await();
                $collectedResults = array_merge($collectedResults, $branchResults);
                $collectedOutputs = array_merge($collectedOutputs, $branchOutputs);
            } catch (ParallelBranchInterruptException $exception) {
                $interrupt ??= $exception;
                $collectedResults = array_merge($collectedResults, $exception->completedResults);
                $collectedOutputs = array_merge($collectedOutputs, $exception->completedOutputs);
            } catch (Throwable $exception) {
                foreach ($futures as $other) {
                    if ($other !== $future) {
                        $other->ignore();
                    }
                }
                throw $exception;
            }
        }

        // Preserve seed + declared branch key order (stable vs completion order).
        $results = $seedResults;
        foreach (array_keys($branches) as $branchId) {
            if (array_key_exists($branchId, $collectedResults)) {
                $results[$branchId] = $collectedResults[$branchId];
            }
        }
        foreach ($collectedResults as $key => $value) {
            if (! array_key_exists($key, $results)) {
                $results[$key] = $value;
            }
        }
        $outputs = array_merge($seedOutputs, $collectedOutputs);

        if ($interrupt instanceof ParallelBranchInterruptException) {
            throw new ParallelBranchInterruptException(
                $interrupt->forkId,
                $interrupt->joinId,
                $interrupt->branchId,
                $interrupt->pendingNodeId,
                $interrupt->outputKey,
                $interrupt->prompt,
                $interrupt->reason,
                $interrupt->pendingState,
                $results,
                $outputs,
            );
        }

        return [$results, $outputs];
    }
}
