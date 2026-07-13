<?php

namespace DigitalElvis\NeuronAIStudio\Http\Controllers;

use DigitalElvis\NeuronAIStudio\Http\Controllers\Concerns\ValidatesChatAttachments;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowTraceResumeJsonController
{
    use ValidatesChatAttachments;

    public function __invoke(Request $request, $threadOrRun, $run = null): JsonResponse
    {
        $runModel = $run instanceof StudioRun 
            ? $run 
            : ($threadOrRun instanceof StudioRun ? $threadOrRun : null);

        if ($runModel === null) {
            $runId = is_string($run) ? $run : (is_string($threadOrRun) ? $threadOrRun : '');
            $runModel = StudioRun::findOrFail($runId);
        }

        if (! config('neuronai-studio.async_runs_enabled')) {
            return response()->json([
                'message' => 'Async workflow runs are disabled. Set NEURONAI_STUDIO_ASYNC_RUNS_ENABLED=true or use the synchronous stream endpoint.',
            ], 501);
        }

        if (! in_array($runModel->status, ['awaiting_input', 'awaiting_tool_approval'], true)) {
            return response()->json([
                'message' => 'Run is not awaiting human input.',
            ], 422);
        }

        $validated = $request->validate([
            'node_id' => 'nullable|string',
            'approval' => 'nullable|in:approve,reject',
        ]);

        $approval = $validated['approval'] ?? null;

        $chat = $this->validateChatPayload($request, requireContent: $approval === null);
        $validated = array_merge($validated, $chat);

        $nodeId = (string) ($validated['node_id'] ?? $runModel->awaiting_node_id ?? '');

        if ($nodeId === '') {
            return response()->json([
                'message' => 'node_id is required when run has no awaiting_node_id.',
            ], 422);
        }

        $runner = app(WorkflowRunner::class);
        $runModel = $runner->dispatchResume(
            $runModel,
            $nodeId,
            $validated['message'],
            $validated['attachments'] ?? [],
            $approval,
        );

        return response()->json([
            'trace_id' => $runModel->id,
            'status' => 'queued',
        ], 202);
    }
}
