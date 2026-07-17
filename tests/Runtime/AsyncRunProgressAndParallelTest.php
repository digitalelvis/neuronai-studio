<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Runtime;

use DigitalElvis\NeuronAIStudio\Runtime\Progress\ProgressBuffer;
use DigitalElvis\NeuronAIStudio\Runtime\Progress\ProgressEmitter;
use DigitalElvis\NeuronAIStudio\Runtime\Parallel\ConcurrentBranchScheduler;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

use function Amp\delay;

class AsyncRunProgressAndParallelTest extends TestCase
{
    public function test_progress_buffer_appends_monotonic_seq(): void
    {
        Cache::flush();
        $buffer = new ProgressBuffer;
        $runId = 'run-progress-1';

        $this->assertSame(1, $buffer->append($runId, 'step_started', ['node_id' => 'a']));
        $this->assertSame(2, $buffer->append($runId, 'step_completed', ['node_id' => 'a']));

        $events = $buffer->readAfter($runId, 0);
        $this->assertCount(2, $events);
        $this->assertSame('step_started', $events[0]['event']);
        $this->assertSame(2, $events[1]['seq']);

        $this->assertCount(1, $buffer->readAfter($runId, 1));
    }

    public function test_progress_emitter_writes_terminal_event(): void
    {
        Cache::flush();
        $emitter = new ProgressEmitter('run-progress-2', flushSteps: false);
        $emitter('step_started', ['node_id' => 'x']);
        $emitter->terminal('completed');

        $events = (new ProgressBuffer)->readAfter('run-progress-2', 0);
        $this->assertSame('run_terminal', $events[array_key_last($events)]['event']);
        $this->assertSame('completed', $events[array_key_last($events)]['data']['status']);
    }

    public function test_concurrent_scheduler_is_faster_than_sequential_with_amp_delay(): void
    {
        config(['neuronai-studio.parallel.concurrency' => 'concurrent']);
        $scheduler = new ConcurrentBranchScheduler;

        $this->assertTrue($scheduler->shouldRunConcurrent(2));

        $started = microtime(true);
        $scheduler->run([
            'a' => static function (): array {
                delay(0.08);

                return [['a' => 1], ['out_a' => 1]];
            },
            'b' => static function (): array {
                delay(0.08);

                return [['b' => 1], ['out_b' => 1]];
            },
        ]);
        $concurrentMs = (microtime(true) - $started) * 1000;

        config(['neuronai-studio.parallel.concurrency' => 'sequential']);
        $started = microtime(true);
        $scheduler->run([
            'a' => static function (): array {
                delay(0.08);

                return [['a' => 1], []];
            },
            'b' => static function (): array {
                delay(0.08);

                return [['b' => 1], []];
            },
        ]);
        $sequentialMs = (microtime(true) - $started) * 1000;

        $this->assertLessThan($sequentialMs * 0.85, $concurrentMs, "concurrent={$concurrentMs} sequential={$sequentialMs}");
    }
}
