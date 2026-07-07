<?php

namespace DigitalElvis\NeuronAIStudio\Http\Controllers\Integration;

use DigitalElvis\NeuronAIStudio\Http\Controllers\Concerns\ValidatesChatAttachments;
use DigitalElvis\NeuronAIStudio\Integration\StreamAdapterRegistry;
use DigitalElvis\NeuronAIStudio\Integration\WorkflowStreamBridge;
use DigitalElvis\NeuronAIStudio\Models\WorkflowTrace;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * External integration endpoint that resumes a workflow paused at a Human node
 * and continues streaming through a neuron-ai wire-protocol adapter until the
 * run completes (or pauses again) (SA-12 / SA-T8). Rehydrates the checkpoint via
 * {@see WorkflowRunner::resume()} and reuses {@see WorkflowStreamBridge}. Kept
 * separate from the internal `WorkflowTraceResumeController` (SA-08).
 */
class WorkflowIntegrateResumeController
{
    use ValidatesChatAttachments;

    public function __invoke(
        Request $request,
        WorkflowTrace $trace,
        string $protocol,
        StreamAdapterRegistry $registry,
        WorkflowRunner $runner,
    ): StreamedResponse {
        abort_unless($registry->isEnabled($protocol), 404, "Unknown stream protocol [{$protocol}].");

        abort_unless(
            in_array($trace->status, ['awaiting_input', 'awaiting_tool_approval'], true),
            422,
            'Workflow trace is not awaiting input.',
        );

        $chat = $this->validateChatPayload($request);
        $message = (string) $chat['message'];
        $attachments = $chat['attachments'] ?? [];

        $nodeId = (string) ($trace->awaiting_node_id ?? '');

        $adapter = $registry->resolve($protocol, (string) $trace->id);

        return response()->stream(function () use ($trace, $runner, $adapter, $nodeId, $message, $attachments) {
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
                    fn (callable $emitter) => $runner->resume($trace, $nodeId, $message, $emitter, $attachments),
                );
            } catch (Throwable $exception) {
                $sink('data: '.json_encode(['error' => $exception->getMessage()], JSON_THROW_ON_ERROR)."\n\n");
            }
        }, 200, $adapter->getHeaders());
    }
}
