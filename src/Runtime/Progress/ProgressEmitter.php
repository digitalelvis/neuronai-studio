<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Progress;

use DigitalElvis\NeuronAIStudio\Models\StudioRun;

class ProgressEmitter
{
    public function __construct(
        protected string $runId,
        protected ProgressBuffer $buffer = new ProgressBuffer,
        protected bool $flushSteps = true,
    ) {}

    /** @param  array<string, mixed>  $data */
    public function __invoke(string $event, array $data = []): void
    {
        $this->buffer->append($this->runId, $event, $data);

        if ($this->flushSteps && in_array($event, ['step_completed', 'branch_completed', 'tool_call', 'tool_result'], true)) {
            $this->flushIncrementalStep($event, $data);
        }
    }

    public function terminal(string $status, array $data = []): void
    {
        $this->buffer->append($this->runId, 'run_terminal', array_merge($data, [
            'status' => $status,
        ]));
    }

    /** @param  array<string, mixed>  $data */
    protected function flushIncrementalStep(string $event, array $data): void
    {
        $run = StudioRun::query()->find($this->runId);
        if ($run === null) {
            return;
        }

        $checkpoint = is_array($run->checkpoint_state) ? $run->checkpoint_state : [];
        $steps = is_array($checkpoint['async_progress_steps'] ?? null) ? $checkpoint['async_progress_steps'] : [];
        $steps[] = [
            'event' => $event,
            'data' => $data,
            'at' => now()->toIso8601String(),
        ];
        // Cap growth for long token streams
        if (count($steps) > 500) {
            $steps = array_slice($steps, -500);
        }
        $checkpoint['async_progress_steps'] = $steps;
        $run->update(['checkpoint_state' => $checkpoint]);
    }
}
