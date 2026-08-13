<?php

namespace DigitalElvis\NeuronAIStudio\Integration;

use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowReplyResolver;
use Illuminate\Support\Str;
use NeuronAI\Chat\Messages\Stream\Adapters\AGUIAdapter;
use NeuronAI\Chat\Messages\Stream\Adapters\StreamAdapterInterface;
use NeuronAI\Chat\Messages\Stream\Chunks\TextChunk;
use NeuronAI\Chat\Messages\Stream\Chunks\ToolCallChunk;
use NeuronAI\Chat\Messages\Stream\Chunks\ToolResultChunk;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolInterface;

/**
 * Bridges the Studio interpreted-runtime SSE events (`token`, `tool_call`,
 * `tool_result`, `step_*`, ...) into neuron-ai wire-protocol output (vercel /
 * agui). Implements AD-008 Option A: convert each Studio event into a Neuron
 * chunk (`TextChunk`/`ToolCallChunk`/`ToolResultChunk`), feed it through the
 * adapter's `transform()`, and emit `start()`/`end()` manually around the run.
 *
 * The internal `WorkflowStreamController` and its SSE remain untouched (SA-08):
 * this bridge only powers the external integration controllers by reusing the
 * same `WorkflowRunner` execution via an emitter callback.
 */
class WorkflowStreamBridge
{
    /** Shared message id so all text deltas belong to one assistant message. */
    protected string $messageId;

    /** Whether any reply-facing text delta was streamed via `token` events. */
    protected bool $streamedText = false;

    /** Human prompt captured from `human_input_required` for channel reply. */
    protected ?string $humanPrompt = null;

    /** @var array<string, mixed> */
    protected array $lastClientState = [];

    /**
     * @param  list<array{id?: string, role: string, content: string}>  $messagesSnapshot
     * @param  array<string, mixed>  $initialClientState
     * @param  null|(callable(): list<array{id?: string, role: string, content: string}>)  $loadMessages
     */
    public function __construct(
        protected StreamAdapterInterface $adapter,
        protected ?WorkflowReplyResolver $replyResolver = null,
        protected array $messagesSnapshot = [],
        protected array $initialClientState = [],
        protected ?string $threadId = null,
        protected ?string $runId = null,
        protected mixed $loadMessages = null,
    ) {
        $this->messageId = 'msg_'.Str::uuid()->toString();
        $this->replyResolver ??= app(WorkflowReplyResolver::class);
        $this->lastClientState = ClientFacingState::of($this->initialClientState);
    }

    /**
     * Drive a workflow run/resume through the adapter, writing protocol output
     * via `$sink`. `$execute` receives the Studio event emitter and must run the
     * workflow (returning the resulting trace).
     *
     * @param  callable(string): void  $sink
     * @param  callable(callable(string, array<string, mixed>): void): StudioRun  $execute
     */
    public function run(callable $sink, callable $execute): StudioRun
    {
        foreach ($this->adapter->start() as $line) {
            $sink($line);
        }

        if ($this->adapter instanceof AGUIAdapter) {
            $sink(AguiProtocol::messagesSnapshot($this->currentMessages()));
            $sink(AguiProtocol::stateSnapshot($this->lastClientState));
        }

        $emitter = function (string $event, array $data) use ($sink): void {
            foreach ($this->convert($event, $data) as $line) {
                $sink($line);
            }
        };

        $run = $execute($emitter);

        // Human pause: publish the prompt as the channel reply (WRC-03).
        if ($run->status === 'awaiting_input' && ! $this->streamedText) {
            $prompt = $this->humanPrompt ?? $this->replyResolver->textFromRun($run);
            if ($prompt !== '') {
                foreach ($this->adapter->transform(new TextChunk($this->messageId, $prompt)) as $line) {
                    $sink($line);
                }
                $this->streamedText = true;
            }
        }

        // Step-boundary fallback when no reply-facing tokens were streamed.
        if (! $this->streamedText && ! in_array($run->status, ['awaiting_input', 'awaiting_tool_approval'], true)) {
            $text = $this->replyResolver->textFromRun($run);

            if ($text !== '') {
                foreach ($this->adapter->transform(new TextChunk($this->messageId, $text)) as $line) {
                    $sink($line);
                }
            }
        }

        $paused = in_array($run->status, ['awaiting_input', 'awaiting_tool_approval'], true);

        if ($paused && $this->adapter instanceof AGUIAdapter) {
            $this->lastClientState = ClientFacingState::of(
                is_array($run->output) ? $run->output : (is_array($run->checkpoint_state['state'] ?? null) ? $run->checkpoint_state['state'] : $this->lastClientState),
            );
            $sink(AguiProtocol::messagesSnapshot($this->currentMessages()));
            $sink(AguiProtocol::stateSnapshot($this->lastClientState));
        }

        if ($paused) {
            foreach ($this->awaitingSignal($run) as $line) {
                $sink($line);
            }
        }

        if ($paused && $this->adapter instanceof AGUIAdapter) {
            foreach ($this->endWithInterrupt($run) as $line) {
                $sink($line);
            }

            return $run;
        }

        foreach ($this->adapter->end() as $line) {
            $sink($line);
        }

        return $run;
    }

    /**
     * Convert a single Studio runtime event into protocol output lines. Only
     * text/tool events map to Neuron chunks; step/trace lifecycle events are
     * handled out-of-band (start/end + awaiting signal) and ignored here.
     *
     * @param  array<string, mixed>  $data
     * @return iterable<string>
     */
    protected function convert(string $event, array $data): iterable
    {
        switch ($event) {
            case 'token':
                // WRC-04: only forward reply-facing streams to the wire protocol.
                if (($data['publish_reply'] ?? true) === false) {
                    return [];
                }

                $delta = (string) ($data['delta'] ?? '');

                if ($delta === '') {
                    return [];
                }

                $this->streamedText = true;

                return $this->adapter->transform(new TextChunk($this->messageId, $delta));

            case 'human_input_required':
                $prompt = (string) ($data['prompt'] ?? '');
                if ($prompt !== '') {
                    $this->humanPrompt = $prompt;
                }

                return [];

            case 'tool_call':
                return $this->adapter->transform(new ToolCallChunk($this->toolFrom($data)));

            case 'tool_result':
                return $this->adapter->transform(new ToolResultChunk($this->toolFrom($data, withResult: true)));

            case 'step_completed':
                return $this->stateDeltaFrom($data);

            default:
                return [];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return iterable<string>
     */
    protected function stateDeltaFrom(array $data): iterable
    {
        if (! $this->adapter instanceof AGUIAdapter) {
            return [];
        }

        if (! is_array($data['state'] ?? null)) {
            return [];
        }

        $next = ClientFacingState::of($data['state']);
        $ops = JsonPatch::diff($this->lastClientState, $next);

        if ($ops === []) {
            return [];
        }

        $this->lastClientState = $next;

        yield AguiProtocol::stateDelta($ops);
    }

    /**
     * Build a Neuron tool carrier from a Studio tool event payload.
     *
     * @param  array<string, mixed>  $data
     */
    protected function toolFrom(array $data, bool $withResult = false): ToolInterface
    {
        $tool = Tool::make((string) ($data['name'] ?? 'tool'));

        $inputs = is_array($data['inputs'] ?? null) ? $data['inputs'] : [];
        $tool->setInputs($inputs);

        if ($withResult) {
            $result = $data['result'] ?? null;
            $tool->setResult(is_string($result) ? $result : json_encode($result));
        }

        return $tool;
    }

    /**
     * Emit a protocol-appropriate terminal signal that the workflow paused and
     * is awaiting input, carrying the `trace_id` the client uses to resume via
     * `traces/{trace}/resume/{protocol}`.
     *
     * @return iterable<string>
     */
    protected function awaitingSignal(StudioRun $run): iterable
    {
        $payload = [
            'status' => $run->status,
            'trace_id' => $run->id,
            'node_id' => $run->awaitingNodeId(),
        ];

        $prompt = $this->humanPrompt ?? $this->replyResolver->textFromRun($run);
        if ($prompt !== '') {
            $payload['prompt'] = $prompt;
        }

        if ($this->adapter instanceof AGUIAdapter) {
            yield 'data: '.json_encode([
                'type' => 'CUSTOM',
                'name' => 'awaiting_input',
                'value' => $payload,
            ], JSON_THROW_ON_ERROR)."\n\n";

            return;
        }

        yield 'data: '.json_encode([
            'type' => 'data-awaiting_input',
            'data' => $payload,
        ], JSON_THROW_ON_ERROR)."\n\n";
    }

    /**
     * Close open AG-UI streams then emit canonical interrupt RUN_FINISHED
     * instead of the adapter's success RUN_FINISHED (AGUI-08 dual-emit).
     *
     * @return iterable<string>
     */
    protected function endWithInterrupt(StudioRun $run): iterable
    {
        foreach ($this->adapter->end() as $line) {
            if (str_contains($line, '"type":"RUN_FINISHED"')) {
                $decoded = json_decode((string) preg_replace('/^data:\s*/', '', trim($line)), true);
                $threadId = $this->threadId ?: (string) ($decoded['threadId'] ?? '');
                $runId = $this->runId ?: (string) ($decoded['runId'] ?? '');

                yield AguiProtocol::runFinishedInterrupt(
                    $threadId,
                    $runId,
                    [[
                        'interruptId' => $run->id,
                        'reason' => $run->status,
                        'payload' => [
                            'status' => $run->status,
                            'trace_id' => $run->id,
                            'node_id' => $run->awaitingNodeId(),
                            'prompt' => $this->humanPrompt ?? $this->replyResolver->textFromRun($run),
                        ],
                    ]],
                );

                continue;
            }

            yield $line;
        }
    }

    /**
     * @return list<array{id?: string, role: string, content: string}>
     */
    protected function currentMessages(): array
    {
        if (is_callable($this->loadMessages)) {
            return ($this->loadMessages)();
        }

        return $this->messagesSnapshot;
    }
}
