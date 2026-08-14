<?php

namespace DigitalElvis\NeuronAIStudio\Integration;

/**
 * Out-of-band AG-UI SSE events that neuron-ai AGUIAdapter does not emit
 * (snapshots, interrupt RUN_FINISHED). Same `data: {json}` framing as the adapter.
 */
final class AguiProtocol
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function sse(array $data): string
    {
        return 'data: '.json_encode($data, JSON_THROW_ON_ERROR)."\n\n";
    }

    /**
     * @param  list<array{id?: string, role: string, content: string}>  $messages
     */
    public static function messagesSnapshot(array $messages): string
    {
        return self::sse([
            'type' => 'MESSAGES_SNAPSHOT',
            'messages' => $messages,
        ]);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function stateSnapshot(array $snapshot): string
    {
        return self::sse([
            'type' => 'STATE_SNAPSHOT',
            'snapshot' => $snapshot,
        ]);
    }

    /**
     * @param  list<array{op: string, path: string, value?: mixed}>  $delta
     */
    public static function stateDelta(array $delta): string
    {
        return self::sse([
            'type' => 'STATE_DELTA',
            'delta' => $delta,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $interrupts
     */
    public static function runFinishedInterrupt(string $threadId, string $runId, array $interrupts): string
    {
        return self::sse([
            'type' => 'RUN_FINISHED',
            'threadId' => $threadId,
            'runId' => $runId,
            'outcome' => [
                'type' => 'interrupt',
                'interrupts' => $interrupts,
            ],
        ]);
    }
}
