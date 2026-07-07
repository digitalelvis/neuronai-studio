<?php

namespace DigitalElvis\NeuronAIStudio\Http\Controllers\Integration;

use DigitalElvis\NeuronAIStudio\Http\Controllers\Concerns\ValidatesChatAttachments;
use DigitalElvis\NeuronAIStudio\Integration\StreamAdapterRegistry;
use DigitalElvis\NeuronAIStudio\Integration\WorkflowStreamBridge;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * External integration endpoint that streams a workflow execution through a
 * neuron-ai wire-protocol adapter (vercel / agui) via {@see WorkflowStreamBridge}
 * (SA-06 / SA-T7). When the workflow pauses at a Human node the stream ends with
 * the `trace_id` the client uses to resume. Fully separate from the internal
 * playground `WorkflowStreamController` (SA-08).
 */
class WorkflowIntegrateStreamController
{
    use ValidatesChatAttachments;

    public function __invoke(
        Request $request,
        WorkflowDefinition $workflow,
        string $protocol,
        StreamAdapterRegistry $registry,
        WorkflowRunner $runner,
    ): StreamedResponse {
        abort_unless($registry->isEnabled($protocol), 404, "Unknown stream protocol [{$protocol}].");

        $validated = $request->validate([
            'thread_id' => 'nullable|uuid',
            'state' => 'nullable|array',
            'context' => 'nullable|array',
        ]);

        $chat = $this->validateChatPayload($request, requireContent: false);
        $validated = array_merge($validated, $chat);

        $threadId = $validated['thread_id'] ?? (string) Str::uuid();
        $state = $validated['state'] ?? $validated['context'] ?? [];

        $input = [
            'message' => (string) ($validated['message'] ?? ''),
            'input' => (string) ($validated['message'] ?? ''),
            'state' => is_array($state) ? $state : [],
            'attachments' => $validated['attachments'] ?? [],
            'thread_id' => $threadId,
        ];

        $adapter = $registry->resolve($protocol, $threadId);

        return response()->stream(function () use ($workflow, $runner, $adapter, $input) {
            $sink = static function (string $chunk): void {
                echo $chunk;

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            };

            try {
                (new WorkflowStreamBridge($adapter))->run(
                    $sink,
                    fn (callable $emitter) => $runner->run($workflow, $input, $emitter),
                );
            } catch (Throwable $exception) {
                $sink('data: '.json_encode(['error' => $exception->getMessage()], JSON_THROW_ON_ERROR)."\n\n");
            }
        }, 200, $adapter->getHeaders());
    }
}
