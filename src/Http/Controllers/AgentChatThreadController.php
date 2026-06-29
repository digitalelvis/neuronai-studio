<?php

namespace DigitalElvis\NeuronAIStudio\Http\Controllers;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Services\ChatThreadLoader;
use Illuminate\Http\JsonResponse;

class AgentChatThreadController
{
    public function __invoke(AgentDefinition $agent, string $thread, ChatThreadLoader $loader): JsonResponse
    {
        return response()->json($loader->loadForAgent($agent->id, $thread));
    }
}
