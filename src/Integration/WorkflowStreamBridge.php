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

    public function __construct(
        protected StreamAdapterInterface $adapter,
        protected ?WorkflowReplyResolver $replyResolver = null,
    ) {
        $this->messageId = 'msg_'.Str::uuid()->toString();
        $this->replyResolver ??= app(WorkflowReplyResolver::class);
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

        if (in_array($run->status, ['awaiting_input', 'awaiting_tool_approval'], true)) {
            foreach ($this->awaitingSignal($run) as $line) {
                $sink($line);
            }
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

            default:
                return [];
        }
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
}
