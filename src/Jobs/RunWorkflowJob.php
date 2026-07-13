<?php

namespace DigitalElvis\NeuronAIStudio\Jobs;

use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunWorkflowJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    /** @param  array<string, mixed>  $input */
    public function __construct(
        public string $runId,
        public int $workflowId,
        public array $input,
    ) {
        $this->onQueue((string) config('neuronai-studio.queue', 'default'));

        $connection = config('neuronai-studio.queue_connection');
        if (is_string($connection) && $connection !== '') {
            $this->onConnection($connection);
        }

        $this->tries = (int) config('neuronai-studio.queue_tries', 1);
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [(int) config('neuronai-studio.queue_backoff', 30)];
    }

    public function handle(WorkflowRunner $runner): void
    {
        $run = StudioRun::findOrFail($this->runId);
        $workflow = WorkflowDefinition::findOrFail($this->workflowId);

        $run->update(['status' => 'running']);

        $runner->runExistingRun($run->fresh(), $workflow, $this->input, emitter: null);
    }

    public function failed(?Throwable $exception): void
    {
        $run = StudioRun::find($this->runId);

        if ($run === null) {
            return;
        }

        $run->update([
            'status' => 'failed',
            'error_message' => $exception?->getMessage() ?? 'Workflow job failed.',
            'finished_at' => now(),
        ]);
    }
}
