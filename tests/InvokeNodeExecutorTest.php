<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\InvokeNodeExecutor;
use DigitalElvis\NeuronAIStudio\Tests\Fixtures\InvokeTestHook;
use RuntimeException;

class InvokeNodeExecutorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'neuronai-studio.invoke_hooks' => [
                InvokeTestHook::class,
            ],
        ]);

        $this->app->bind(InvokeTestHook::class, fn () => new InvokeTestHook);
    }

    public function test_invokes_allowlisted_hook_and_writes_output(): void
    {
        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, ['input' => 'hello']);
        $executor = new InvokeNodeExecutor;

        $handle = $executor->execute([
            'data' => [
                'hook_class' => InvokeTestHook::class,
                'output_key' => 'enriched',
            ],
        ], $state, $context);

        $this->assertSame('default', $handle);
        $this->assertSame('hello-hooked', $state->get('enriched'));
    }

    public function test_defaults_output_key_to_invoke_result(): void
    {
        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, ['input' => 'x']);
        $executor = new InvokeNodeExecutor;

        $executor->execute([
            'data' => [
                'hook_class' => InvokeTestHook::class,
            ],
        ], $state, $context);

        $this->assertSame('x-hooked', $state->get('invoke_result'));
    }

    public function test_rejects_missing_hook_class(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires data.hook_class');

        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, []);
        (new InvokeNodeExecutor)->execute(['data' => []], $state, $context);
    }

    public function test_rejects_empty_allowlist(): void
    {
        config(['neuronai-studio.invoke_hooks' => []]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not in config');

        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, []);
        (new InvokeNodeExecutor)->execute([
            'data' => ['hook_class' => InvokeTestHook::class],
        ], $state, $context);
    }

    public function test_rejects_class_outside_allowlist(): void
    {
        config(['neuronai-studio.invoke_hooks' => ['App\\Other\\Hook']]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not in config');

        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, []);
        (new InvokeNodeExecutor)->execute([
            'data' => ['hook_class' => InvokeTestHook::class],
        ], $state, $context);
    }
}
