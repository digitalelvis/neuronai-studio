<?php

namespace DigitalElvis\NeuronAIStudio\Http\Controllers\Integration;

use DigitalElvis\NeuronAIStudio\Http\Controllers\Concerns\ValidatesChatAttachments;
use DigitalElvis\NeuronAIStudio\Integration\StreamAdapterRegistry;
use DigitalElvis\NeuronAIStudio\Integration\WorkflowStreamBridge;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
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
        $threadOrRun,
        $runOrProtocol = null,
        $protocolString = null,
        ?StreamAdapterRegistry $registry = null,
    ): StreamedResponse {
        // Resolve $run model and $protocol dynamically based on route parameters
        $runModel = $runOrProtocol instanceof StudioRun 
            ? $runOrProtocol 
            : ($threadOrRun instanceof StudioRun ? $threadOrRun : null);

        if ($runModel === null) {
            $runId = is_string($runOrProtocol) && \Illuminate\Support\Str::isUuid($runOrProtocol) 
                ? $runOrProtocol 
                : (is_string($threadOrRun) && \Illuminate\Support\Str::isUuid($threadOrRun) ? $threadOrRun : '');
            $runModel = StudioRun::findOrFail($runId);
        }

        // The protocol is the parameter that is a string and not the run ID
        $protocol = is_string($protocolString) && $protocolString !== ''
            ? $protocolString
            : (is_string($runOrProtocol) && ! \Illuminate\Support\Str::isUuid($runOrProtocol) ? $runOrProtocol : '');

        if ($protocol === '') {
            $protocol = is_string($threadOrRun) && ! \Illuminate\Support\Str::isUuid($threadOrRun) ? $threadOrRun : '';
        }

        $registry = $registry ?: app(StreamAdapterRegistry::class);

        abort_unless($registry->isEnabled($protocol), 404, "Unknown stream protocol [{$protocol}].");

        abort_unless(
            in_array($runModel->status, ['awaiting_input', 'awaiting_tool_approval'], true),
            422,
            'Workflow run is not awaiting input.',
        );

        $chat = $this->validateChatPayload($request);
        $message = (string) $chat['message'];
        $attachments = $chat['attachments'] ?? [];

        $nodeId = (string) ($runModel->awaiting_node_id ?? '');

        $adapter = $registry->resolve($protocol, (string) $runModel->id);

        return response()->stream(function () use ($runModel, $adapter, $nodeId, $message, $attachments) {
            $sink = static function (string $chunk): void {
                echo $chunk;

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            };

            try {
                $runner = app(WorkflowRunner::class);
                (new WorkflowStreamBridge($adapter))->run(
                    $sink,
                    fn (callable $emitter) => $runner->resume($runModel, $nodeId, $message, $emitter, $attachments),
                );
            } catch (Throwable $exception) {
                $sink('data: '.json_encode(['error' => $exception->getMessage()], JSON_THROW_ON_ERROR)."\n\n");
            }
        }, 200, $adapter->getHeaders());
    }
}
