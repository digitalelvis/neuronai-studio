<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\Checkpoint\CheckpointService;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\CheckpointingExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\NodeExecutorInterface;
use NeuronAI\Workflow\WorkflowState;
use Illuminate\Support\Str;

class CheckpointServiceTest extends TestCase
{
    protected function trace(): StudioRun
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Checkpoint Flow',
            'slug' => 'checkpoint-flow-'.uniqid(),
            'graph' => WorkflowDefinition::defaultGraph(),
        ]);

        $thread = StudioThread::create([
            'id' => (string) Str::uuid(),
            'entity_type' => WorkflowDefinition::class,
            'entity_id' => $workflow->id,
        ]);

        return StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => $thread->id,
            'status' => 'running',
            'input' => ['input' => 'x'],
            'started_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $data */
    protected function state(string $traceId, array $data = []): BuilderWorkflowState
    {
        $context = new GraphContext([], []);

        return new BuilderWorkflowState($context, $traceId, array_merge([
            '__workflow_trace_id' => $traceId,
            'input' => 'hello',
        ], $data));
    }

    protected function counting(): NodeExecutorInterface
    {
        return new class implements NodeExecutorInterface
        {
            public int $calls = 0;

            public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
            {
                $this->calls++;
                $state->set('reply', 'value-'.$this->calls);

                return 'default';
            }
        };
    }

    protected function decorator(NodeExecutorInterface $inner): CheckpointingExecutor
    {
        return new CheckpointingExecutor($inner, app(CheckpointService::class));
    }

    public function test_first_run_stores_checkpoint_and_resume_skips_inner(): void
    {
        $trace = $this->trace();
        $inner = $this->counting();
        $decorator = $this->decorator($inner);
        $context = new GraphContext([], []);
        $config = ['id' => 'agent_1', 'data' => ['checkpoint' => true]];

        $decorator->execute($config, $this->state($trace->id), $context);

        $this->assertSame(1, $inner->calls);
        $trace->refresh();
        $this->assertCount(1, $trace->checkpoint_state['node_checkpoints'] ?? []);

        // A fresh state models a resume: the node's output is not present yet.
        $resumeState = $this->state($trace->id);
        $decorator->execute($config, $resumeState, $context);

        $this->assertSame(1, $inner->calls, 'Checkpointed node must not re-run the inner executor on resume.');
        $this->assertSame('value-1', $resumeState->get('reply'));
    }

    public function test_loop_checkpoints_are_scoped_by_iteration(): void
    {
        $trace = $this->trace();
        $inner = $this->counting();
        $decorator = $this->decorator($inner);
        $context = new GraphContext([], []);
        $config = ['id' => 'agent_1', 'data' => ['checkpoint' => true]];

        $decorator->execute($config, $this->state($trace->id, ['__loop_iterations' => ['loop_1' => 1]]), $context);
        $decorator->execute($config, $this->state($trace->id, ['__loop_iterations' => ['loop_1' => 2]]), $context);

        $this->assertSame(2, $inner->calls, 'Each loop iteration must get its own checkpoint.');
        $trace->refresh();
        $this->assertCount(2, $trace->checkpoint_state['node_checkpoints'] ?? []);
    }

    public function test_disabled_checkpoints_always_delegate(): void
    {
        config(['neuronai-studio.checkpoints.enabled' => false]);

        $trace = $this->trace();
        $inner = $this->counting();
        $decorator = $this->decorator($inner);
        $context = new GraphContext([], []);
        $config = ['id' => 'agent_1', 'data' => ['checkpoint' => true]];

        $decorator->execute($config, $this->state($trace->id), $context);
        $decorator->execute($config, $this->state($trace->id), $context);

        $this->assertSame(2, $inner->calls);
        $trace->refresh();
        $this->assertEmpty($trace->checkpoint_state['node_checkpoints'] ?? []);
    }

    public function test_missing_checkpoint_flag_delegates_without_persisting(): void
    {
        $trace = $this->trace();
        $inner = $this->counting();
        $decorator = $this->decorator($inner);
        $context = new GraphContext([], []);
        $config = ['id' => 'agent_1', 'data' => []];

        $decorator->execute($config, $this->state($trace->id), $context);
        $decorator->execute($config, $this->state($trace->id), $context);

        $this->assertSame(2, $inner->calls);
        $trace->refresh();
        $this->assertEmpty($trace->checkpoint_state['node_checkpoints'] ?? []);
    }

    public function test_input_change_invalidates_checkpoint(): void
    {
        $trace = $this->trace();
        $inner = $this->counting();
        $decorator = $this->decorator($inner);
        $context = new GraphContext([], []);
        $config = ['id' => 'agent_1', 'data' => ['checkpoint' => true]];

        $decorator->execute($config, $this->state($trace->id, ['input' => 'first']), $context);
        $decorator->execute($config, $this->state($trace->id, ['input' => 'second']), $context);

        $this->assertSame(2, $inner->calls, 'Changed input state must invalidate the checkpoint.');
    }

    public function test_expired_checkpoint_returns_null_on_lookup(): void
    {
        $trace = $this->trace();
        config(['neuronai-studio.checkpoints.ttl' => 60]);

        $service = app(CheckpointService::class);
        $service->store($trace->id, 'agent_1', 0, 'hash', ['reply' => 'x'], 'default');

        $trace->refresh();
        $state = $trace->checkpoint_state;
        $state['node_checkpoints']['agent_1_0']['expires_at'] = now()->subMinutes(2)->toIso8601String();
        $trace->update(['checkpoint_state' => $state]);

        $this->assertNull($service->lookup($trace->id, 'agent_1', 0));
    }
}
