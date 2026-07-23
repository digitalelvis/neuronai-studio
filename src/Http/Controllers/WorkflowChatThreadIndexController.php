<?php

namespace DigitalElvis\NeuronAIStudio\Http\Controllers;

use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Services\ChatThreadIndex;
use Illuminate\Http\JsonResponse;

class WorkflowChatThreadIndexController
{
    public function __invoke(WorkflowDefinition $workflow, ChatThreadIndex $index): JsonResponse
    {
        return response()->json([
            'data' => $index->listForWorkflow($workflow->id),
        ]);
    }
}
