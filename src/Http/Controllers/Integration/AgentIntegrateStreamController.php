<?php

namespace DigitalElvis\NeuronAIStudio\Http\Controllers\Integration;

use DigitalElvis\NeuronAIStudio\Http\Controllers\Concerns\ValidatesChatAttachments;
use DigitalElvis\NeuronAIStudio\Integration\AguiProtocol;
use DigitalElvis\NeuronAIStudio\Integration\RunAgentInputParser;
use DigitalElvis\NeuronAIStudio\Integration\StreamAdapterRegistry;
use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Services\ChatThreadLoader;
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
        RunAgentInputParser $parser,
        ChatThreadLoader $threads,
    ): StreamedResponse {
        abort_unless($registry->isEnabled($protocol), 404, "Unknown stream protocol [{$protocol}].");

        if ($protocol === 'agui') {
            $validated = $parser->parse($request);
            $this->validateOptionalParameters($request);
        } else {
            $validated = $this->validateStreamRequest($request, [
                'thread_id' => 'nullable|uuid',
                'context' => 'nullable|array',
                'parameters' => 'nullable|array',
                'parameters.temperature' => 'nullable|numeric|min:0|max:2',
                'parameters.top_p' => 'nullable|numeric|min:0|max:1',
                'parameters.max_tokens' => 'nullable|integer|min:1',
            ]);

            $chat = $this->validateChatPayload($request);
            $validated = array_merge($validated, $chat);
        }

        $adapter = $registry->resolve(
            $protocol,
            $validated['thread_id'] ?? null,
            $validated['run_id'] ?? null,
        );

        return response()->stream(function () use ($agent, $runner, $adapter, $validated, $protocol, $threads) {
            try {
                $handler = $runner->streamHandler($agent, $validated);
                $injected = false;

                foreach ($handler->events($adapter) as $output) {
                    echo $output;

                    if (
                        ! $injected
                        && $protocol === 'agui'
                        && str_contains($output, '"type":"RUN_STARTED"')
                    ) {
                        $history = $threads->loadForAgent($agent->id, (string) ($validated['thread_id'] ?? ''));
                        echo AguiProtocol::messagesSnapshot($history['messages']);
                        echo AguiProtocol::stateSnapshot([]);
                        $injected = true;
                    }

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

    protected function validateOptionalParameters(Request $request): void
    {
        if (! $request->exists('parameters')) {
            return;
        }

        $this->validateStreamRequest($request, [
            'parameters' => 'nullable|array',
            'parameters.temperature' => 'nullable|numeric|min:0|max:2',
            'parameters.top_p' => 'nullable|numeric|min:0|max:1',
            'parameters.max_tokens' => 'nullable|integer|min:1',
        ]);
    }
}
