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
        $input = ['input' => $request->string('input', 'Hello from workflow test')->toString()];

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
