<?php

namespace DigitalElvis\NeuronAIStudio\Runtime;

use Illuminate\Support\Arr;
use NeuronAI\Workflow\WorkflowState;

/**
 * Build trace/checkpoint snapshots without nesting volatile workflow keys.
 *
 * {@see GraphStepExecutorNode::recordStep} previously stored $state->all() inside __steps,
 * which already contained __steps — exponential growth on long graphs.
 */
final class WorkflowStateSnapshot
{
    /** @var list<string> */
    private const VOLATILE_KEYS = [
        '__steps',
        '__current_node_id',
        '__studio_current_step',
        '__loop_iterations',
        '__parallel_resume',
        '__parallel_results',
        '__tool_approval_resume',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function forTrace(WorkflowState $state): array
    {
        return Arr::except($state->all(), self::VOLATILE_KEYS);
    }
}
