<?php

namespace DigitalElvis\NeuronAIStudio\McpServer;

use DigitalElvis\NeuronAIStudio\Models\McpEndpoint;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use Illuminate\Support\Str;

class McpInvocationRecorder
{
    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>|null  $output
     */
    public function record(
        McpEndpoint $endpoint,
        string $toolName,
        array $input,
        ?array $output = null,
        ?string $errorMessage = null,
        string $status = 'completed',
    ): StudioRun {
        $thread = StudioThread::query()->create([
            'id' => (string) Str::uuid(),
            'entity_type' => McpEndpoint::class,
            'entity_id' => (string) $endpoint->id,
        ]);

        return StudioRun::query()->create([
            'id' => (string) Str::uuid(),
            'thread_id' => $thread->id,
            'status' => $status,
            'input' => [
                'source' => 'mcp_endpoint',
                'endpoint_slug' => $endpoint->slug,
                'tool' => $toolName,
                'arguments' => $input,
            ],
            'output' => $output,
            'error_message' => $errorMessage,
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }
}
