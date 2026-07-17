<?php

namespace DigitalElvis\NeuronAIStudio\Jobs;

use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Runtime\Progress\ProgressEmitter;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ResumeWorkflowJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    /** @param  array<int, array<string, mixed>>  $attachments */
    public function __construct(
        public string $runId,
        public string $nodeId,
        public string $message,
        public array $attachments = [],
        public ?string $approval = null,
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

        $run->update(['status' => 'running']);

        $emitter = $this->makeProgressEmitter();

        try {
            $result = $runner->resume(
                $run->fresh(),
                $this->nodeId,
                $this->message,
                emitter: $emitter,
                attachments: $this->attachments,
                approval: $this->approval,
            );
            $emitter?->terminal((string) $result->status);
        } catch (Throwable $exception) {
            $emitter?->terminal('failed', ['error' => $exception->getMessage()]);
            throw $exception;
        }
    }

    protected function makeProgressEmitter(): ?ProgressEmitter
    {
        if (! (bool) config('neuronai-studio.async_progress.enabled', true)) {
            return null;
        }

        return new ProgressEmitter($this->runId);
    }

    public function failed(?Throwable $exception): void
    {
        $run = StudioRun::find($this->runId);

        if ($run === null) {
            return;
        }

        $run->update([
            'status' => 'failed',
            'error_message' => $exception?->getMessage() ?? 'Workflow resume job failed.',
            'finished_at' => now(),
        ]);
    }
}
