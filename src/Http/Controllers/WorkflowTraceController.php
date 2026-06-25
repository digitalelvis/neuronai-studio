<?php

namespace ElvisLopesDigital\NeuronAIStudio\Http\Controllers;

use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowDefinition;
use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowTrace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowTraceController
{
    public function index(Request $request, WorkflowDefinition $workflow): JsonResponse
    {
        $perPage = min((int) $request->integer('per_page', 25), 100);

        $traces = $workflow->traces()
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $traces->getCollection()->map(fn (WorkflowTrace $trace) => $this->traceSummary($trace)),
            'meta' => [
                'current_page' => $traces->currentPage(),
                'last_page' => $traces->lastPage(),
                'per_page' => $traces->perPage(),
                'total' => $traces->total(),
            ],
        ]);
    }

    public function show(WorkflowTrace $trace): JsonResponse
    {
        $trace->load(['workflow', 'steps']);

        return response()->json([
            'trace' => array_merge($this->traceSummary($trace), [
                'error_message' => $trace->error_message,
                'input' => $trace->input,
                'output' => $trace->output,
                'workflow_name' => $trace->workflow?->name,
            ]),
            'steps' => $trace->steps->map(fn ($step) => [
                'id' => $step->id,
                'node_type' => $step->node_type,
                'node_id' => $step->node_id,
                'duration_ms' => $step->duration_ms,
                'state_snapshot' => $step->state_snapshot,
            ])->values(),
        ]);
    }

    /** @return array<string, mixed> */
    protected function traceSummary(WorkflowTrace $trace): array
    {
        $inputPreview = null;
        if (is_array($trace->input)) {
            $inputPreview = $trace->input['message']
                ?? $trace->input['input']
                ?? (count($trace->input) ? json_encode($trace->input) : null);
        }

        return [
            'id' => $trace->id,
            'status' => $trace->status,
            'input_preview' => is_string($inputPreview) ? mb_substr($inputPreview, 0, 120) : null,
            'started_at' => $trace->started_at?->toIso8601String(),
            'finished_at' => $trace->finished_at?->toIso8601String(),
            'duration_ms' => $trace->durationMs(),
        ];
    }
}
