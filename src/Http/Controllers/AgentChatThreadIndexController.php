<?php

namespace DigitalElvis\NeuronAIStudio\Http\Controllers;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Services\ChatThreadIndex;
use Illuminate\Http\JsonResponse;

class AgentChatThreadIndexController
{
    public function __invoke(AgentDefinition $agent, ChatThreadIndex $index): JsonResponse
    {
        return response()->json([
            'data' => $index->listForAgent($agent->id),
        ]);
    }
}
