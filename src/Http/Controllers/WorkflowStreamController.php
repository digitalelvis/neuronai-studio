<?php

namespace DigitalElvis\NeuronAIStudio\Http\Controllers;

use DigitalElvis\NeuronAIStudio\Http\Controllers\Concerns\ValidatesChatAttachments;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class WorkflowStreamController
{
    use ValidatesChatAttachments;

    public function __invoke(Request $request, WorkflowDefinition $workflow, WorkflowRunner $runner): StreamedResponse
    {
        if ($request->isMethod('GET')) {
            return $this->stream($workflow, $runner, [
                'message' => $request->string('input', 'Hello from workflow test')->toString(),
                'input' => $request->string('input', 'Hello from workflow test')->toString(),
            ]);
        }

        $validated = $request->validate([
            'state' => 'nullable|array',
            'thread_id' => 'nullable|uuid',
        ]);

        $chat = $this->validateChatPayload($request);
        $validated = array_merge($validated, $chat);
        $validated['thread_id'] = $validated['thread_id'] ?? (string) Str::uuid();

        $payload = [
            'message' => (string) ($validated['message'] ?? ''),
            'input' => (string) ($validated['message'] ?? ''),
            'state' => $validated['state'] ?? [],
            'attachments' => $validated['attachments'] ?? [],
            'thread_id' => $validated['thread_id'],
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

            $threadId = isset($input['thread_id']) && is_string($input['thread_id']) && $input['thread_id'] !== ''
                ? $input['thread_id']
                : (string) Str::uuid();

            $input['thread_id'] = $threadId;

            $send('thread', ['thread_id' => $threadId]);

            $send('trace_started', [
                'workflow_id' => $workflow->id,
            ]);

            try {
                $trace = $runner->run($workflow, $input, $send);

                if ($trace->status === 'awaiting_input') {
                    return;
                }

                $send('trace_completed', [
                    'trace_id' => $trace->id,
                    'status' => $trace->status,
                    'output' => $trace->output,
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
