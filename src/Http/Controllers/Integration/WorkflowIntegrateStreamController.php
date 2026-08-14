<?php

namespace DigitalElvis\NeuronAIStudio\Http\Controllers\Integration;

use DigitalElvis\NeuronAIStudio\Http\Controllers\Concerns\ValidatesChatAttachments;
use DigitalElvis\NeuronAIStudio\Integration\RunAgentInputParser;
use DigitalElvis\NeuronAIStudio\Integration\StreamAdapterRegistry;
use DigitalElvis\NeuronAIStudio\Integration\WorkflowStreamBridge;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use DigitalElvis\NeuronAIStudio\Services\ChatThreadLoader;
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
        RunAgentInputParser $parser,
        ChatThreadLoader $threads,
    ): StreamedResponse {
        abort_unless($registry->isEnabled($protocol), 404, "Unknown stream protocol [{$protocol}].");

        if ($protocol === 'agui') {
            $parsed = $parser->parse($request, requireContent: false);

            if ($parsed['resume'] !== []) {
                return $this->resumeAgui(
                    $parsed,
                    $workflow,
                    $protocol,
                    $registry,
                    $runner,
                    $parser,
                    $threads,
                );
            }

            $threadId = $parsed['thread_id'];
            $runId = $parsed['run_id'];
            $input = [
                'message' => $parsed['message'],
                'input' => $parsed['message'],
                'state' => $parsed['state'],
                'attachments' => $parsed['attachments'],
                'thread_id' => $threadId,
            ];
        } else {
            $validated = $this->validateStreamRequest($request, [
                'thread_id' => 'nullable|uuid',
                'state' => 'nullable|array',
                'context' => 'nullable|array',
            ]);

            $chat = $this->validateChatPayload($request, requireContent: false);
            $validated = array_merge($validated, $chat);

            $threadId = $validated['thread_id'] ?? (string) Str::uuid();
            $runId = null;
            $state = $validated['state'] ?? $validated['context'] ?? [];

            $input = [
                'message' => (string) ($validated['message'] ?? ''),
                'input' => (string) ($validated['message'] ?? ''),
                'state' => is_array($state) ? $state : [],
                'attachments' => $validated['attachments'] ?? [],
                'thread_id' => $threadId,
            ];
        }

        $adapter = $registry->resolve($protocol, $threadId, $runId);

        return $this->streamBridge(
            $adapter,
            $threads,
            $workflow->id,
            $threadId,
            $runId,
            fn (callable $emitter) => $runner->run($workflow, $input, $emitter),
            is_array($input['state'] ?? null) ? $input['state'] : [],
        );
    }

    /**
     * @param  array{thread_id: string, run_id: string, message: string, state: array<string, mixed>, resume: list<array<string, mixed>>, attachments: array<int, mixed>}  $parsed
     */
    protected function resumeAgui(
        array $parsed,
        WorkflowDefinition $workflow,
        string $protocol,
        StreamAdapterRegistry $registry,
        WorkflowRunner $runner,
        RunAgentInputParser $parser,
        ChatThreadLoader $threads,
    ): StreamedResponse {
        $interruptId = $parser->interruptId($parsed['resume']);

        if ($interruptId === '' || ! Str::isUuid($interruptId)) {
            abort(404, 'Unknown interrupt.');
        }

        $run = StudioRun::query()->with('thread')->findOrFail($interruptId);

        abort_unless(
            $run->thread?->entity_type === WorkflowDefinition::class
                && (int) $run->thread->entity_id === (int) $workflow->id,
            404,
            'Unknown interrupt.',
        );

        abort_unless(
            in_array($run->status, ['awaiting_input', 'awaiting_tool_approval'], true),
            422,
            'Workflow run is not awaiting input.',
        );

        $message = $parser->resumeMessage($parsed['resume']);
        if ($message === '') {
            $message = $parsed['message'];
        }
        $approval = $parser->resumeApproval($parsed['resume']);
        $attachments = $parsed['attachments'];
        $nodeId = (string) ($run->awaitingNodeId() ?? '');

        $threadId = $parsed['thread_id'] !== ''
            ? $parsed['thread_id']
            : (string) $run->thread_id;
        $runId = $parsed['run_id'];

        $adapter = $registry->resolve($protocol, $threadId, $runId);

        return $this->streamBridge(
            $adapter,
            $threads,
            $workflow->id,
            $threadId,
            $runId,
            fn (callable $emitter) => $runner->resume($run, $nodeId, $message, $emitter, $attachments, $approval),
            is_array($run->output) ? $run->output : [],
        );
    }

    /**
     * @param  callable(callable(string, array<string, mixed>): void): StudioRun  $execute
     * @param  array<string, mixed>  $initialState
     */
    protected function streamBridge(
        $adapter,
        ChatThreadLoader $threads,
        int $workflowId,
        string $threadId,
        ?string $runId,
        callable $execute,
        array $initialState,
    ): StreamedResponse {
        $loadMessages = fn () => $threads->loadForWorkflow($workflowId, $threadId)['messages'];

        return response()->stream(function () use ($adapter, $execute, $loadMessages, $initialState, $threadId, $runId) {
            $sink = static function (string $chunk): void {
                echo $chunk;

                if (ob_get_level() > 0) {
                    ob_flush();
                }

                flush();
            };

            try {
                (new WorkflowStreamBridge(
                    adapter: $adapter,
                    messagesSnapshot: $loadMessages(),
                    initialClientState: $initialState,
                    threadId: $threadId,
                    runId: $runId,
                    loadMessages: $loadMessages,
                ))->run($sink, $execute);
            } catch (Throwable $exception) {
                $sink('data: '.json_encode(['error' => $exception->getMessage()], JSON_THROW_ON_ERROR)."\n\n");
            }
        }, 200, $adapter->getHeaders());
    }
}
