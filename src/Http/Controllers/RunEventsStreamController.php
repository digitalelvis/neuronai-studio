<?php

namespace DigitalElvis\NeuronAIStudio\Http\Controllers;

use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Runtime\Progress\ProgressBuffer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RunEventsStreamController
{
    public function __invoke(Request $request, StudioRun $run, ProgressBuffer $buffer): StreamedResponse
    {
        $after = (int) ($request->query('after') ?? $request->headers->get('Last-Event-ID', 0));
        $pollMs = max(50, (int) config('neuronai-studio.async_progress.poll_ms', 200));

        return response()->stream(function () use ($run, $buffer, $after, $pollMs): void {
            $cursor = $after;
            $started = microtime(true);
            $maxSeconds = (int) config('neuronai-studio.async_progress.ttl', 3600);

            while (! connection_aborted()) {
                $events = $buffer->readAfter($run->id, $cursor);

                foreach ($events as $event) {
                    $cursor = (int) $event['seq'];
                    echo 'id: '.$cursor."\n";
                    echo 'event: '.$event['event']."\n";
                    echo 'data: '.json_encode($event['data'] ?? [], JSON_UNESCAPED_UNICODE)."\n\n";

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();

                    if (($event['event'] ?? '') === 'run_terminal') {
                        return;
                    }
                }

                $run->refresh();
                if (in_array($run->status, ['completed', 'failed', 'cancelled'], true) && $events === []) {
                    echo "event: run_terminal\n";
                    echo 'data: '.json_encode(['status' => $run->status], JSON_UNESCAPED_UNICODE)."\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();

                    return;
                }

                if ((microtime(true) - $started) > $maxSeconds) {
                    return;
                }

                usleep($pollMs * 1000);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
