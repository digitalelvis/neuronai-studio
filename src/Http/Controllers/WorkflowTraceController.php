<?php

namespace DigitalElvis\NeuronAIStudio\Http\Controllers;

use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowTraceController
{
    public function index(Request $request, WorkflowDefinition $workflow): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 25), 100);

        $runs = $workflow->runs()
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $runs->getCollection()->map(fn (StudioRun $run) => $this->runSummary($run)),
            'meta' => [
                'current_page' => $runs->currentPage(),
                'last_page' => $runs->lastPage(),
                'per_page' => $runs->perPage(),
                'total' => $runs->total(),
            ],
        ]);
    }

    public function show(StudioRun $run): JsonResponse
    {
        $run->load(['thread.entity', 'traces.spans']);
        $trace = $run->traces()->latest()->first();
        $spans = $trace ? $trace->spans()->orderBy('started_at')->get() : collect();

        return response()->json([
            'trace' => array_merge($this->runSummary($run), [
                'error_message' => $run->error_message,
                'input' => $run->input,
                'output' => $run->output,
                'workflow_name' => $run->thread?->entity?->name,
                'prompt_tokens' => $run->prompt_tokens,
                'completion_tokens' => $run->completion_tokens,
                'total_tokens' => $run->total_tokens,
            ]),
            'steps' => $spans->map(fn ($span) => [
                'id' => $span->id,
                'node_type' => $span->type,
                'node_id' => $span->name,
                'duration_ms' => $span->duration_ms,
                'state_snapshot' => $span->output['state_snapshot'] ?? null,
                'prompt_tokens' => $span->prompt_tokens,
                'completion_tokens' => $span->completion_tokens,
                'total_tokens' => $span->total_tokens,
            ])->values(),
        ]);
    }

    /** @return array<string, mixed> */
    protected function runSummary(StudioRun $run): array
    {
        $inputPreview = null;
        if (is_array($run->input)) {
            $inputPreview = $run->input['message']
                ?? $run->input['input']
                ?? (count($run->input) ? json_encode($run->input) : null);
        }

        return [
            'id' => $run->id,
            'status' => $run->status,
            'awaiting_node_id' => $run->awaitingNodeId(),
            'input_preview' => is_string($inputPreview) ? mb_substr($inputPreview, 0, 120) : null,
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'duration_ms' => $run->durationMs(),
            'prompt_tokens' => $run->prompt_tokens,
            'completion_tokens' => $run->completion_tokens,
            'total_tokens' => $run->total_tokens,
        ];
    }
}
