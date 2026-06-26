<?php

namespace ElvisLopesDigital\NeuronAIStudio\Http\Controllers;

use ElvisLopesDigital\NeuronAIStudio\Models\AgentDefinition;
use ElvisLopesDigital\NeuronAIStudio\Runtime\AgentRunner;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use NeuronAI\Chat\Messages\Stream\Chunks\TextChunk;
use NeuronAI\Chat\Messages\Stream\Chunks\ToolCallChunk;
use NeuronAI\Chat\Messages\Stream\Chunks\ToolResultChunk;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AgentChatStreamController
{
    public function __invoke(Request $request, AgentDefinition $agent, AgentRunner $runner): StreamedResponse
    {
        $validated = $request->validate([
            'message' => 'required|string',
            'thread_id' => 'nullable|uuid',
            'instructions' => 'nullable|string',
            'context' => 'nullable|array',
            'parameters' => 'nullable|array',
            'parameters.temperature' => 'nullable|numeric|min:0|max:2',
            'parameters.top_p' => 'nullable|numeric|min:0|max:1',
            'parameters.max_tokens' => 'nullable|integer|min:1',
            'attachments' => 'nullable|array',
            'attachments.*.type' => 'required_with:attachments|string',
            'attachments.*.mime_type' => 'nullable|string',
            'attachments.*.storage_key' => 'required_with:attachments|string',
            'attachments.*.name' => 'nullable|string',
        ]);

        $validated['thread_id'] = $validated['thread_id'] ?? (string) Str::uuid();

        return response()->stream(function () use ($agent, $runner, $validated) {
            $send = $this->emitter();
            $thread = $runner->resolveThread($agent, $validated);

            try {
                $send('thread', ['thread_id' => $thread['public_id']]);

                foreach ($runner->stream($agent, $validated) as $event) {
                    if ($event instanceof TextChunk) {
                        $send('token', ['delta' => $event->content]);
                    }

                    if ($event instanceof ToolCallChunk) {
                        $send('tool_call', [
                            'name' => $event->tool->getName(),
                            'inputs' => $event->tool->getInputs(),
                        ]);
                    }

                    if ($event instanceof ToolResultChunk) {
                        $send('tool_result', [
                            'name' => $event->tool->getName(),
                            'result' => $event->tool->getResult(),
                        ]);
                    }
                }

                $send('done', []);
            } catch (Throwable $exception) {
                $send('error', ['message' => $exception->getMessage()]);
            }
        }, 200, $this->streamHeaders());
    }

    /** @return callable(string, array): void */
    protected function emitter(): callable
    {
        return function (string $event, array $data): void {
            echo "event: {$event}\n";
            echo 'data: '.json_encode($data, JSON_THROW_ON_ERROR)."\n\n";

            if (ob_get_level() > 0) {
                ob_flush();
            }

            flush();
        };
    }

    /** @return array<string, string> */
    protected function streamHeaders(): array
    {
        return [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];
    }
}
