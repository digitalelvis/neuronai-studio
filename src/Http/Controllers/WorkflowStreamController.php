<?php

namespace ElvisLopesDigital\NeuronAIStudio\Http\Controllers;

use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowDefinition;
use ElvisLopesDigital\NeuronAIStudio\Runtime\WorkflowRunner;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class WorkflowStreamController
{
    public function __invoke(Request $request, WorkflowDefinition $workflow, WorkflowRunner $runner): StreamedResponse
    {
        if ($request->isMethod('GET')) {
            return $this->stream($workflow, $runner, [
                'message' => $request->string('input', 'Hello from workflow test')->toString(),
                'input' => $request->string('input', 'Hello from workflow test')->toString(),
            ]);
        }

        $validated = $request->validate([
            'message' => 'nullable|string',
            'state' => 'nullable|array',
            'attachments' => 'nullable|array',
        ]);

        $payload = [
            'message' => (string) ($validated['message'] ?? ''),
            'input' => (string) ($validated['message'] ?? ''),
            'state' => $validated['state'] ?? [],
            'attachments' => $validated['attachments'] ?? [],
        ];

        return $this->stream($workflow, $runner, $payload);
    }

    /** @param  array<string, mixed>  $input */
    protected function stream(WorkflowDefinition $workflow, WorkflowRunner $runner, array $input): StreamedResponse
    {
        return response()->stream(function () use ($workflow, $runner, $input) {
            $send = function (string $event, array $data): void {
                echo "event: {$event}\n";
                echo 'data: '.json_encode($data, JSON_THROW_ON_ERROR)."\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            };

            $send('run_started', [
                'workflow_id' => $workflow->id,
            ]);

            try {
                $run = $runner->run($workflow, $input, $send);

                if ($run->status === 'awaiting_input') {
                    return;
                }

                $send('run_completed', [
                    'run_id' => $run->id,
                    'status' => $run->status,
                    'output' => $run->output,
                ]);
            } catch (Throwable $exception) {
                $send('run_failed', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
