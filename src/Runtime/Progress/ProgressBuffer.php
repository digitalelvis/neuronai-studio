<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Progress;

use Illuminate\Support\Facades\Cache;

class ProgressBuffer
{
    public function key(string $runId): string
    {
        return 'neuronai-studio:run-progress:'.$runId;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function append(string $runId, string $event, array $data = []): int
    {
        $key = $this->key($runId);
        $ttl = (int) config('neuronai-studio.async_progress.ttl', 3600);
        $payload = Cache::get($key, ['seq' => 0, 'events' => []]);

        if (! is_array($payload)) {
            $payload = ['seq' => 0, 'events' => []];
        }

        $seq = (int) ($payload['seq'] ?? 0) + 1;
        $events = is_array($payload['events'] ?? null) ? $payload['events'] : [];
        $events[] = [
            'seq' => $seq,
            'event' => $event,
            'data' => $data,
            'at' => now()->toIso8601String(),
        ];

        Cache::put($key, ['seq' => $seq, 'events' => $events], $ttl);

        return $seq;
    }

    /**
     * @return array<int, array{seq: int, event: string, data: array<string, mixed>, at: string}>
     */
    public function readAfter(string $runId, int $afterSeq = 0): array
    {
        $payload = Cache::get($this->key($runId), ['seq' => 0, 'events' => []]);
        $events = is_array($payload['events'] ?? null) ? $payload['events'] : [];

        return array_values(array_filter(
            $events,
            static fn (array $event): bool => (int) ($event['seq'] ?? 0) > $afterSeq,
        ));
    }

    public function clear(string $runId): void
    {
        Cache::forget($this->key($runId));
    }
}
