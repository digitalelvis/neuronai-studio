<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Runtime\Memory;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\AgentNodeExecutor;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use ReflectionMethod;

class AgentNodeMemoryOverrideTest extends TestCase
{
    public function test_node_override_extracts_memory_keys(): void
    {
        $definition = AgentDefinition::create([
            'name' => 'Node Memory Agent',
            'slug' => 'node-memory-'.uniqid(),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Test',
            'tools' => [],
            'memory_config' => [
                'context_window' => 8000,
                'driver' => 'eloquent',
            ],
        ]);

        $executor = app(AgentNodeExecutor::class);
        $method = new ReflectionMethod($executor, 'memoryOverrideConfig');
        $method->setAccessible(true);

        $override = $method->invoke($executor, [
            'context_window' => 500,
            'driver' => 'in_memory',
            'tool_max_runs' => 3,
        ], $definition);

        $this->assertSame([
            'context_window' => 500,
            'driver' => 'in_memory',
        ], $override);
    }

    public function test_empty_node_override_returns_empty_array(): void
    {
        $executor = app(AgentNodeExecutor::class);
        $method = new ReflectionMethod($executor, 'memoryOverrideConfig');
        $method->setAccessible(true);

        $override = $method->invoke($executor, [
            'agent_id' => 1,
            'tool_max_runs' => 2,
        ], null);

        $this->assertSame([], $override);
    }

    public function test_node_override_extracts_budget_keys(): void
    {
        $executor = app(AgentNodeExecutor::class);
        $method = new ReflectionMethod($executor, 'memoryOverrideConfig');
        $method->setAccessible(true);

        $override = $method->invoke($executor, [
            'budget_rag' => 200,
            'budget_tool_results' => 400,
            'budget_state' => 100,
            'tool_max_runs' => 3,
        ], null);

        $this->assertSame([
            'budget_rag' => 200,
            'budget_tool_results' => 400,
            'budget_state' => 100,
        ], $override);
    }
}
