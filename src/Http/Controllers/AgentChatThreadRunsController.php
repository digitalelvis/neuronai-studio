<?php

namespace DigitalElvis\NeuronAIStudio\Http\Controllers;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Services\ChatThreadIndex;
use Illuminate\Http\JsonResponse;

class AgentChatThreadRunsController
{
    public function __invoke(AgentDefinition $agent, string $thread, ChatThreadIndex $index): JsonResponse
    {
        return response()->json([
            'data' => $index->runsForAgentThread($agent->id, $thread),
        ]);
    }
}
