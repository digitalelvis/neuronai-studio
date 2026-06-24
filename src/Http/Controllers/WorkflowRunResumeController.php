<?php

namespace ElvisLopesDigital\NeuronAIStudio\Http\Controllers;

use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowRun;
use ElvisLopesDigital\NeuronAIStudio\Runtime\WorkflowRunner;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class WorkflowRunResumeController
{
    public function __invoke(Request $request, WorkflowRun $run, WorkflowRunner $runner): StreamedResponse
    {
        $validated = $request->validate([
            'message' => 'required|string',
            'node_id' => 'required|string',
        ]);

        return response()->stream(function () use ($run, $runner, $validated) {
            $send = function (string $event, array $data): void {
                echo "event: {$event}\n";
                echo 'data: '.json_encode($data, JSON_THROW_ON_ERROR)."\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            };

            try {
                $result = $runner->resume(
                    $run,
                    $validated['node_id'],
                    $validated['message'],
                    $send,
                );

                if ($result->status === 'awaiting_input') {
                    return;
                }

                $send('run_completed', [
                    'run_id' => $result->id,
                    'status' => $result->status,
                    'output' => $result->output,
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
