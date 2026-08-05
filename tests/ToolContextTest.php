<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\Tools\InteractsWithToolContext;
use DigitalElvis\NeuronAIStudio\Runtime\Tools\ToolContext;
use DigitalElvis\NeuronAIStudio\Runtime\Tools\ToolContextAware;
use DigitalElvis\NeuronAIStudio\Runtime\Tools\ToolContextInjector;
use DigitalElvis\NeuronAIStudio\Support\PlaygroundContext;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\Toolkits\AbstractToolkit;
use NeuronAI\Workflow\WorkflowState;
use ReflectionClass;

class ToolContextTest extends TestCase
{
    public function test_from_array_filters_internal_keys(): void
    {
        $context = ToolContext::fromArray([
            'integration_context' => ['account_id' => 68],
            'include_history' => true,
            '__studio_run_id' => 'secret-run',
            '__tool_runs' => ['x' => 1],
            'input' => 'hello',
        ]);

        $this->assertTrue($context->has('integration_context'));
        $this->assertTrue($context->has('include_history'));
        $this->assertTrue($context->has('input'));
        $this->assertFalse($context->has('__studio_run_id'));
        $this->assertFalse($context->has('__tool_runs'));
        $this->assertSame(68, $context->get('integration_context.account_id'));
    }

    public function test_from_workflow_state(): void
    {
        $state = new WorkflowState([
            'integration_context' => ['user_id' => 13],
            '__studio_thread_id' => 'thread-1',
        ]);

        $context = ToolContext::fromWorkflowState($state);

        $this->assertSame(13, $context->get('integration_context.user_id'));
        $this->assertFalse($context->has('__studio_thread_id'));
    }

    public function test_injector_sets_context_on_aware_tool_only(): void
    {
        $aware = new class extends Tool implements ToolContextAware
        {
            use InteractsWithToolContext;

            public function __construct()
            {
                parent::__construct('aware_tool', 'Aware');
            }

            protected function properties(): array
            {
                return [
                    ToolProperty::make('q', PropertyType::STRING, 'Query', true),
                ];
            }

            public function __invoke(string $q): string
            {
                return (string) $this->contextGet('integration_context.account_id');
            }
        };

        $plain = new class extends Tool
        {
            public function __construct()
            {
                parent::__construct('plain_tool', 'Plain');
            }

            protected function properties(): array
            {
                return [];
            }

            public function __invoke(): string
            {
                return 'ok';
            }
        };

        $context = ToolContext::fromArray([
            'integration_context' => ['account_id' => 68],
        ]);

        ToolContextInjector::apply($aware, $context);
        ToolContextInjector::apply($plain, $context);

        $this->assertSame(68, $aware->contextGet('integration_context.account_id'));
        $propertyNames = array_map(fn ($p) => $p->getName(), $aware->getProperties());
        $this->assertSame(['q'], $propertyNames);
        $this->assertNotContains('account_id', $propertyNames);
        $this->assertNotContains('integration_context', $propertyNames);
    }

    public function test_injector_applies_context_to_toolkit_children(): void
    {
        $child = new class extends Tool implements ToolContextAware
        {
            use InteractsWithToolContext;

            public function __construct()
            {
                parent::__construct('child_aware', 'Child');
            }

            protected function properties(): array
            {
                return [];
            }

            public function __invoke(): string
            {
                return (string) $this->contextGet('plan_slug', '');
            }
        };

        $toolkit = new class($child) extends AbstractToolkit
        {
            public function __construct(protected Tool $child) {}

            public function provide(): array
            {
                return [$this->child];
            }
        };

        $context = ToolContext::fromArray(['plan_slug' => 'cnh-protegida']);
        $wrapped = ToolContextInjector::apply($toolkit, $context);

        $tools = $wrapped->tools();
        $this->assertCount(1, $tools);
        $this->assertInstanceOf(ToolContextAware::class, $tools[0]);
        $this->assertSame('cnh-protegida', $tools[0]->contextGet('plan_slug'));
    }

    public function test_make_agent_injects_tool_context_from_config(): void
    {
        $aware = new class extends Tool implements ToolContextAware
        {
            use InteractsWithToolContext;

            public function __construct()
            {
                parent::__construct('cfg_aware', 'Cfg');
            }

            protected function properties(): array
            {
                return [];
            }

            public function __invoke(): string
            {
                return (string) $this->contextGet('integration_context.account_id', 0);
            }
        };

        $definition = AgentDefinition::query()->create([
            'name' => 'Context Agent',
            'slug' => 'context-agent-'.uniqid(),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
            'tools' => [],
        ]);

        $runner = app(AgentRunner::class);
        $method = (new ReflectionClass($runner))->getMethod('makeAgent');
        $method->setAccessible(true);

        $agent = $method->invoke($runner, $definition, [
            'provider' => $definition->provider,
            'model' => $definition->model,
            'instructions' => $definition->instructions,
            'tools' => [$aware],
            'tool_context' => ToolContext::fromArray([
                'integration_context' => ['account_id' => 99],
            ]),
        ]);

        $bootstrapped = $agent->bootstrapTools();
        $this->assertCount(1, $bootstrapped);
        $this->assertInstanceOf(ToolContextAware::class, $bootstrapped[0]);
        $this->assertSame(99, $bootstrapped[0]->contextGet('integration_context.account_id'));
    }

    public function test_playground_config_sets_tool_context_and_keeps_prompt_augmentation(): void
    {
        $definition = AgentDefinition::query()->create([
            'name' => 'Playground Context',
            'slug' => 'playground-context-'.uniqid(),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Base instructions.',
            'tools' => [],
        ]);

        $runner = app(AgentRunner::class);
        $method = (new ReflectionClass($runner))->getMethod('resolvePlaygroundConfig');
        $method->setAccessible(true);

        $payload = [
            'context' => [
                'integration_context' => [
                    'account_id' => 68,
                    'channel' => 'chatwoot',
                ],
                'include_history' => true,
            ],
        ];

        $config = $method->invoke($runner, $definition, $payload);

        $this->assertInstanceOf(ToolContext::class, $config['tool_context']);
        $this->assertSame(68, $config['tool_context']->get('integration_context.account_id'));
        $this->assertStringContainsString('Runtime context', $config['instructions']);
        $this->assertStringContainsString('account_id', $config['instructions']);

        $normalized = PlaygroundContext::normalize($payload['context']);
        $this->assertSame(68, $normalized['integration_context']['account_id']);
    }
}
