<?php

namespace ElvisLopesDigital\NeuronAIStudio\Http\Controllers;

use ElvisLopesDigital\NeuronAIStudio\Models\AgentDefinition;
use ElvisLopesDigital\NeuronAIStudio\Services\ChatThreadLoader;
use Illuminate\Http\JsonResponse;

class AgentChatThreadController
{
    public function __invoke(AgentDefinition $agent, string $thread, ChatThreadLoader $loader): JsonResponse
    {
        return response()->json($loader->loadForAgent($agent->id, $thread));
    }
}
