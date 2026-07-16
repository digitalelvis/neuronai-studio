<?php

namespace DigitalElvis\NeuronAIStudio\Http\Controllers\Integration;

use Carbon\Carbon;
use DigitalElvis\NeuronAIStudio\Usage\UsageQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class UsageExportController extends Controller
{
    public function __construct(
        protected UsageQuery $usageQuery = new UsageQuery,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'entity_type' => ['sometimes', 'nullable', 'string', Rule::in(['agent', 'workflow'])],
            'entity_id' => ['sometimes', 'nullable'],
            'group_by' => ['sometimes', 'nullable', 'string', Rule::in(['model', 'entity'])],
            'model' => ['sometimes', 'nullable', 'string'],
        ]);

        $from = Carbon::parse($validated['from'])->startOfDay();
        $to = Carbon::parse($validated['to'])->endOfDay();

        $result = $this->usageQuery->aggregate(
            $from,
            $to,
            $validated['entity_type'] ?? null,
            $validated['entity_id'] ?? null,
            $validated['group_by'] ?? null,
            $validated['model'] ?? null,
        );

        return response()->json([
            'currency' => $result['currency'],
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'totals' => [
                'prompt_tokens' => $result['prompt_tokens'],
                'completion_tokens' => $result['completion_tokens'],
                'total_tokens' => $result['total_tokens'],
                'estimated_cost' => $result['estimated_cost'],
                'run_count' => $result['run_count'],
            ],
            'breakdown' => $result['breakdown'],
        ]);
    }

    public function showRun(string $run): JsonResponse
    {
        $detail = $this->usageQuery->runDetail($run);

        if ($detail === null) {
            abort(404);
        }

        return response()->json($detail);
    }
}
