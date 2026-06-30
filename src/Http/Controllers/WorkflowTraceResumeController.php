<?php

namespace DigitalElvis\NeuronAIStudio\Http\Controllers;

use DigitalElvis\NeuronAIStudio\Http\Controllers\Concerns\ValidatesChatAttachments;
use DigitalElvis\NeuronAIStudio\Models\WorkflowTrace;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class WorkflowTraceResumeController
{
    use ValidatesChatAttachments;

    public function __invoke(Request $request, WorkflowTrace $trace, WorkflowRunner $runner): StreamedResponse
    {
        $validated = $request->validate([
            'node_id' => 'required|string',
        ]);

        $chat = $this->validateChatPayload($request);
        $validated = array_merge($validated, $chat);

        return response()->stream(function () use ($trace, $runner, $validated) {
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
                    $trace,
                    $validated['node_id'],
                    $validated['message'],
                    $send,
                    $validated['attachments'] ?? [],
                );

                if ($result->status === 'awaiting_input') {
                    return;
                }

                $send('trace_completed', [
                    'trace_id' => $result->id,
                    'status' => $result->status,
                    'output' => $result->output,
                ]);
            } catch (Throwable $exception) {
                $send('trace_failed', [
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
