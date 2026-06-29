<?php

namespace DigitalElvis\NeuronAIStudio\Evaluation;

use DigitalElvis\NeuronAIStudio\Models\EvalRun;
use DigitalElvis\NeuronAIStudio\Models\EvalRunItem;
use NeuronAI\Evaluation\AssertionFailure;
use NeuronAI\Evaluation\Contracts\EvaluationOutputInterface;
use NeuronAI\Evaluation\Runner\EvaluatorResult;
use NeuronAI\Evaluation\Runner\EvaluatorSummary;

class EloquentEvaluationOutput implements EvaluationOutputInterface
{
    public function __construct(
        protected EvalRun $run,
    ) {}

    public function output(EvaluatorSummary $summary): void
    {
        $this->run->update([
            'status' => $summary->hasFailures() ? 'completed_with_failures' : 'completed',
            'passed_count' => $summary->getPassedCount(),
            'failed_count' => $summary->getFailedCount(),
            'success_rate' => $summary->getSuccessRate(),
            'total_time_ms' => (int) round($summary->getTotalExecutionTime() * 1000),
            'finished_at' => now(),
        ]);

        foreach ($summary->getResults() as $result) {
            EvalRunItem::create([
                'eval_run_id' => $this->run->id,
                'case_index' => $result->getIndex(),
                'input' => $result->getInput(),
                'output' => $this->stringifyOutput($result->getOutput()),
                'passed' => $result->isPassed(),
                'failures' => $this->serializeFailures($result->getAssertionFailures()),
                'scores' => $result->getAssertionScores(),
                'execution_time_ms' => (int) round($result->getExecutionTime() * 1000),
                'error_message' => $result->getError(),
            ]);
        }
    }

    protected function stringifyOutput(mixed $output): ?string
    {
        if ($output === null) {
            return null;
        }

        if (is_string($output)) {
            return $output;
        }

        if (is_scalar($output)) {
            return (string) $output;
        }

        return json_encode($output, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<AssertionFailure>  $failures
     * @return array<int, array<string, mixed>>
     */
    protected function serializeFailures(array $failures): array
    {
        return array_map(fn (AssertionFailure $failure) => [
            'message' => $failure->getMessage(),
            'assertion' => $failure->getAssertionMethod(),
            'line' => $failure->getLineNumber(),
            'description' => $failure->getFullDescription(),
            'context' => $failure->getContext(),
        ], $failures);
    }
}
