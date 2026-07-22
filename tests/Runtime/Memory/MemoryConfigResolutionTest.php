<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Runtime\Memory;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\DynamicAgent;
use DigitalElvis\NeuronAIStudio\Runtime\Memory\MemoryConfig;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use ReflectionObject;

class MemoryConfigResolutionTest extends TestCase
{
    public function test_null_memory_config_leaves_context_window_null(): void
    {
        $definition = $this->makeAgentDefinition();

        $agent = app(AgentRunner::class)->resolveAgent($definition);

        $this->assertNull($this->readContextWindow($agent));
        $this->assertTrue($this->readMemoryConfig($agent)->isInherit());
    }

    public function test_agent_memory_config_reaches_dynamic_agent(): void
    {
        $definition = $this->makeAgentDefinition([
            'memory_config' => [
                'context_window' => 4000,
                'driver' => 'eloquent',
                'summarization_enabled' => true,
            ],
        ]);

        $agent = app(AgentRunner::class)->resolveAgent($definition);

        $this->assertSame(4000, $this->readContextWindow($agent));
        $memory = $this->readMemoryConfig($agent);
        $this->assertSame(4000, $memory->contextWindow());
        $this->assertSame(MemoryConfig::DRIVER_ELOQUENT, $memory->driver());
        $this->assertTrue($memory->summarizationEnabled());
    }

    public function test_config_override_wins_over_agent_envelope(): void
    {
        $definition = $this->makeAgentDefinition([
            'memory_config' => [
                'context_window' => 8000,
                'driver' => 'eloquent',
                'summarization_enabled' => false,
            ],
        ]);

        $runner = app(AgentRunner::class);
        $method = new \ReflectionMethod($runner, 'makeAgent');
        $method->setAccessible(true);

        /** @var DynamicAgent $agent */
        $agent = $method->invoke($runner, $definition, [
            'provider' => $definition->provider,
            'model' => $definition->model,
            'instructions' => $definition->instructions,
            'tools' => [],
            'context_window' => 1000,
            'driver' => 'in_memory',
        ]);

        $this->assertSame(1000, $this->readContextWindow($agent));
        $memory = $this->readMemoryConfig($agent);
        $this->assertSame(1000, $memory->contextWindow());
        $this->assertSame(MemoryConfig::DRIVER_IN_MEMORY, $memory->driver());
        $this->assertFalse($memory->summarizationEnabled());
    }

    public function test_nested_memory_config_override_wins(): void
    {
        $definition = $this->makeAgentDefinition([
            'memory_config' => ['context_window' => 5000],
        ]);

        $runner = app(AgentRunner::class);
        $method = new \ReflectionMethod($runner, 'makeAgent');
        $method->setAccessible(true);

        /** @var DynamicAgent $agent */
        $agent = $method->invoke($runner, $definition, [
            'provider' => $definition->provider,
            'model' => $definition->model,
            'instructions' => $definition->instructions,
            'tools' => [],
            'memory_config' => ['context_window' => 250],
        ]);

        $this->assertSame(250, $this->readContextWindow($agent));
    }

    public function test_chat_history_uses_resolved_window(): void
    {
        $definition = $this->makeAgentDefinition([
            'memory_config' => ['context_window' => 4000],
        ]);

        $agent = app(AgentRunner::class)->resolveAgent($definition);

        $method = new \ReflectionMethod($agent, 'chatHistory');
        $method->setAccessible(true);
        $history = $method->invoke($agent);

        $ref = new ReflectionObject($history);
        $prop = $ref->getProperty('contextWindow');
        $prop->setAccessible(true);

        $this->assertSame(4000, $prop->getValue($history));
    }

    public function test_budget_resolution_none_agent_and_override(): void
    {
        $runner = app(AgentRunner::class);

        $none = $runner->resolveMemoryConfig($this->makeAgentDefinition());
        $this->assertNull($none->budgetRag());
        $this->assertNull($none->budgetToolResults());
        $this->assertNull($none->budgetState());

        $agentDefault = $runner->resolveMemoryConfig($this->makeAgentDefinition([
            'memory_config' => [
                'budget_rag' => 800,
                'budget_tool_results' => 1000,
                'budget_state' => 500,
            ],
        ]));
        $this->assertSame(800, $agentDefault->budgetRag());
        $this->assertSame(1000, $agentDefault->budgetToolResults());
        $this->assertSame(500, $agentDefault->budgetState());

        $overrideWins = $runner->resolveMemoryConfig(
            $this->makeAgentDefinition([
                'memory_config' => ['budget_rag' => 800, 'budget_state' => 500],
            ]),
            ['budget_rag' => 200],
        );
        $this->assertSame(200, $overrideWins->budgetRag());
        $this->assertSame(500, $overrideWins->budgetState());
        $this->assertNull($overrideWins->budgetToolResults());

        $overrideAlone = $runner->resolveMemoryConfig(
            $this->makeAgentDefinition(),
            ['budget_tool_results' => 300],
        );
        $this->assertNull($overrideAlone->budgetRag());
        $this->assertSame(300, $overrideAlone->budgetToolResults());
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function makeAgentDefinition(array $extra = []): AgentDefinition
    {
        return AgentDefinition::create(array_merge([
            'name' => 'Memory Agent',
            'slug' => 'memory-agent-'.uniqid(),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Test',
            'tools' => [],
        ], $extra));
    }

    private function readContextWindow(DynamicAgent $agent): ?int
    {
        $ref = new ReflectionObject($agent);
        $prop = $ref->getProperty('contextWindow');
        $prop->setAccessible(true);

        return $prop->getValue($agent);
    }

    private function readMemoryConfig(DynamicAgent $agent): MemoryConfig
    {
        $ref = new ReflectionObject($agent);
        $prop = $ref->getProperty('memoryConfig');
        $prop->setAccessible(true);

        return $prop->getValue($agent);
    }
}
