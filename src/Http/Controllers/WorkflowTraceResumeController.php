<?php

namespace DigitalElvis\NeuronAIStudio\Http\Controllers;

use DigitalElvis\NeuronAIStudio\Http\Controllers\Concerns\ValidatesChatAttachments;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\WorkflowExecutionException;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\StructuredOutputValidationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class WorkflowTraceResumeController
{
    use ValidatesChatAttachments;

    public function __invoke(Request $request, $threadOrRun, $run = null): StreamedResponse
    {
        $runModel = $run instanceof StudioRun 
            ? $run 
            : ($threadOrRun instanceof StudioRun ? $threadOrRun : null);

        if ($runModel === null) {
            $runId = is_string($run) ? $run : (is_string($threadOrRun) ? $threadOrRun : '');
            $runModel = StudioRun::findOrFail($runId);
        }

        $validated = $request->validate([
            'node_id' => 'required|string',
            'approval' => 'nullable|in:approve,reject',
        ]);

        $approval = $validated['approval'] ?? null;

        $chat = $this->validateChatPayload($request, requireContent: $approval === null);
        $validated = array_merge($validated, $chat);

        return response()->stream(function () use ($runModel, $validated, $approval) {
            $send = function (string $event, array $data): void {
                echo "event: {$event}\n";
                echo 'data: '.json_encode($data, JSON_THROW_ON_ERROR)."\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            };

            try {
                $runner = app(WorkflowRunner::class);
                $result = $runner->resume(
                    $runModel,
                    $validated['node_id'],
                    $validated['message'],
                    $send,
                    $validated['attachments'] ?? [],
                    $approval,
                );

                if (in_array($result->status, ['awaiting_input', 'awaiting_tool_approval'], true)) {
                    return;
                }

                $send('trace_completed', [
                    'trace_id' => $result->id,
                    'status' => $result->status,
                    'output' => $result->output,
                ]);
            } catch (StructuredOutputValidationException $exception) {
                $send('trace_failed', [
                    'message' => $exception->getMessage(),
                    'validation_errors' => $exception->validationErrors,
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
