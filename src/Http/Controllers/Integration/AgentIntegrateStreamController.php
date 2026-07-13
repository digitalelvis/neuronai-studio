<?php

namespace DigitalElvis\NeuronAIStudio\Http\Controllers\Integration;

use DigitalElvis\NeuronAIStudio\Http\Controllers\Concerns\ValidatesChatAttachments;
use DigitalElvis\NeuronAIStudio\Integration\StreamAdapterRegistry;
use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * External integration endpoint that streams an agent response through a
 * neuron-ai wire-protocol adapter (vercel / agui). Completely separate from
 * the internal playground `AgentChatStreamController` (SA-08).
 */
class AgentIntegrateStreamController
{
    use ValidatesChatAttachments;

    public function __invoke(
        Request $request,
        AgentDefinition $agent,
        string $protocol,
        StreamAdapterRegistry $registry,
        AgentRunner $runner,
    ): StreamedResponse {
        abort_unless($registry->isEnabled($protocol), 404, "Unknown stream protocol [{$protocol}].");

        $validated = $request->validate([
            'thread_id' => 'nullable|uuid',
            'context' => 'nullable|array',
            'parameters' => 'nullable|array',
            'parameters.temperature' => 'nullable|numeric|min:0|max:2',
            'parameters.top_p' => 'nullable|numeric|min:0|max:1',
            'parameters.max_tokens' => 'nullable|integer|min:1',
        ]);

        $chat = $this->validateChatPayload($request);
        $validated = array_merge($validated, $chat);

        $adapter = $registry->resolve($protocol, $validated['thread_id'] ?? null);

        return response()->stream(function () use ($agent, $runner, $adapter, $validated) {
            try {
                $handler = $runner->streamHandler($agent, $validated);

                foreach ($handler->events($adapter) as $output) {
                    echo $output;

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }

                    flush();
                }
            } catch (Throwable $exception) {
                echo 'data: '.json_encode(['error' => $exception->getMessage()], JSON_THROW_ON_ERROR)."\n\n";

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            }
        }, 200, $adapter->getHeaders());
    }
}
