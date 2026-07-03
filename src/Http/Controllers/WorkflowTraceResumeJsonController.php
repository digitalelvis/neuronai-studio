<?php

namespace DigitalElvis\NeuronAIStudio\Http\Controllers;

use DigitalElvis\NeuronAIStudio\Http\Controllers\Concerns\ValidatesChatAttachments;
use DigitalElvis\NeuronAIStudio\Models\WorkflowTrace;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowTraceResumeJsonController
{
    use ValidatesChatAttachments;

    public function __invoke(Request $request, WorkflowTrace $trace, WorkflowRunner $runner): JsonResponse
    {
        if (! config('neuronai-studio.async_runs_enabled')) {
            return response()->json([
                'message' => 'Async workflow runs are disabled. Set NEURONAI_STUDIO_ASYNC_RUNS_ENABLED=true or use the synchronous stream endpoint.',
            ], 501);
        }

        if (! in_array($trace->status, ['awaiting_input', 'awaiting_tool_approval'], true)) {
            return response()->json([
                'message' => 'Trace is not awaiting human input.',
            ], 422);
        }

        $validated = $request->validate([
            'node_id' => 'nullable|string',
            'approval' => 'nullable|in:approve,reject',
        ]);

        $approval = $validated['approval'] ?? null;

        $chat = $this->validateChatPayload($request, requireContent: $approval === null);
        $validated = array_merge($validated, $chat);

        $nodeId = (string) ($validated['node_id'] ?? $trace->awaiting_node_id ?? '');

        if ($nodeId === '') {
            return response()->json([
                'message' => 'node_id is required when trace has no awaiting_node_id.',
            ], 422);
        }

        $trace = $runner->dispatchResume(
            $trace,
            $nodeId,
            $validated['message'],
            $validated['attachments'] ?? [],
            $approval,
        );

        return response()->json([
            'trace_id' => $trace->id,
            'status' => 'queued',
        ], 202);
    }
}
