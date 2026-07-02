<?php

namespace DigitalElvis\NeuronAIStudio\Http\Controllers;

use DigitalElvis\NeuronAIStudio\Http\Controllers\Concerns\ValidatesChatAttachments;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkflowRunController
{
    use ValidatesChatAttachments;

    public function __invoke(Request $request, WorkflowDefinition $workflow, WorkflowRunner $runner): JsonResponse
    {
        if (! config('neuronai-studio.async_runs_enabled')) {
            return response()->json([
                'message' => 'Async workflow runs are disabled. Set NEURONAI_STUDIO_ASYNC_RUNS_ENABLED=true or use the synchronous stream endpoint.',
            ], 501);
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

        $trace = $runner->dispatch($workflow, $payload);

        return response()->json([
            'trace_id' => $trace->id,
            'status' => 'queued',
            'thread_id' => $payload['thread_id'],
        ], 202);
    }
}
