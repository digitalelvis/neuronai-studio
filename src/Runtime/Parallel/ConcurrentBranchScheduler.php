<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Parallel;

use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\ParallelBranchInterruptException;
use Throwable;

use function Amp\async;

/**
 * Runs branch callables concurrently via Amp fibers when enabled, otherwise sequentially.
 *
 * When more than one branch raises {@see ParallelBranchInterruptException} in the same
 * concurrent tick, the interrupt with the lowest declared branch order is surfaced first;
 * siblings keep running so their results land in the checkpoint snapshot.
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
            return $this->runSequential($branches, $results, $outputs);
        }

        $futures = [];
        foreach ($branches as $branchId => $callable) {
            $futures[$branchId] = async($callable);
        }

        /** @var list<ParallelBranchInterruptException> $interrupts */
        $interrupts = [];
        $collectedResults = [];
        $collectedOutputs = [];

        foreach ($futures as $branchId => $future) {
            try {
                [$branchResults, $branchOutputs] = $future->await();
                $collectedResults = array_merge($collectedResults, $branchResults);
                $collectedOutputs = array_merge($collectedOutputs, $branchOutputs);
            } catch (ParallelBranchInterruptException $exception) {
                $interrupts[] = $exception;
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
        $results = $this->mergeOrderedResults($branches, $seedResults, $collectedResults);
        $outputs = array_merge($seedOutputs, $collectedOutputs);

        if ($interrupts !== []) {
            throw $this->rethrowInterrupt(
                $this->selectInterrupt($interrupts, array_keys($branches)),
                $results,
                $outputs,
            );
        }

        return [$results, $outputs];
    }

    /**
     * @param  array<string, callable(): array{0: array<string, mixed>, 1: array<string, mixed>}>  $branches
     * @param  array<string, mixed>  $results
     * @param  array<string, mixed>  $outputs
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    protected function runSequential(array $branches, array $results, array $outputs): array
    {
        foreach ($branches as $branchId => $callable) {
            try {
                [$branchResults, $branchOutputs] = $callable();
                $results = array_merge($results, $branchResults);
                $outputs = array_merge($outputs, $branchOutputs);
            } catch (ParallelBranchInterruptException $exception) {
                throw $this->rethrowInterrupt(
                    $exception,
                    array_merge($results, $exception->completedResults),
                    array_merge($outputs, $exception->completedOutputs),
                );
            }
        }

        return [$results, $outputs];
    }

    /**
     * Deterministic choice when multiple branches interrupt in the same tick:
     * lowest declared branch order wins.
     *
     * @param  list<ParallelBranchInterruptException>  $interrupts
     * @param  list<string|int>  $branchOrder
     */
    protected function selectInterrupt(array $interrupts, array $branchOrder): ParallelBranchInterruptException
    {
        if (count($interrupts) === 1) {
            return $interrupts[0];
        }

        $rank = array_flip(array_map('strval', $branchOrder));
        usort($interrupts, static function (ParallelBranchInterruptException $a, ParallelBranchInterruptException $b) use ($rank): int {
            return ($rank[$a->branchId] ?? PHP_INT_MAX) <=> ($rank[$b->branchId] ?? PHP_INT_MAX);
        });

        return $interrupts[0];
    }

    /**
     * @param  array<string, mixed>  $branches
     * @param  array<string, mixed>  $seedResults
     * @param  array<string, mixed>  $collectedResults
     * @return array<string, mixed>
     */
    protected function mergeOrderedResults(array $branches, array $seedResults, array $collectedResults): array
    {
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

        return $results;
    }

    /**
     * @param  array<string, mixed>  $results
     * @param  array<string, mixed>  $outputs
     */
    protected function rethrowInterrupt(
        ParallelBranchInterruptException $interrupt,
        array $results,
        array $outputs,
    ): ParallelBranchInterruptException {
        return new ParallelBranchInterruptException(
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
            $interrupt->pendingTools,
            $interrupt->serializedInterrupt,
        );
    }
}
