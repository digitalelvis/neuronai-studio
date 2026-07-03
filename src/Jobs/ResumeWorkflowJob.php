<?php

namespace DigitalElvis\NeuronAIStudio\Jobs;

use DigitalElvis\NeuronAIStudio\Models\WorkflowTrace;
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
        public int $traceId,
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
        $trace = WorkflowTrace::findOrFail($this->traceId);

        $trace->update(['status' => 'running']);

        $runner->resume(
            $trace->fresh(),
            $this->nodeId,
            $this->message,
            emitter: null,
            attachments: $this->attachments,
            approval: $this->approval,
        );
    }

    public function failed(?Throwable $exception): void
    {
        $trace = WorkflowTrace::find($this->traceId);

        if ($trace === null) {
            return;
        }

        $trace->update([
            'status' => 'failed',
            'error_message' => $exception?->getMessage() ?? 'Workflow resume job failed.',
            'finished_at' => now(),
        ]);
    }
}
