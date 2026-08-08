<?php

namespace DigitalElvis\NeuronAIStudio\Http\Controllers;

use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Services\ChatThreadLoader;
use Illuminate\Http\JsonResponse;

class WorkflowChatThreadController
{
    public function __invoke(WorkflowDefinition $workflow, string $thread, ChatThreadLoader $loader): JsonResponse
    {
        return response()->json($loader->loadForWorkflow($workflow->id, $thread));
    }
}
