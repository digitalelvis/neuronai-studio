<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Runtime;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\DynamicAgent;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use ReflectionObject;

class AgentToolControlsTest extends TestCase
{
    public function test_make_agent_applies_tool_max_runs_and_parallel_flag(): void
    {
        $definition = AgentDefinition::create([
            'name' => 'Controls Agent',
            'slug' => 'controls-agent',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Test',
            'tools' => [],
            'tool_max_runs' => 3,
            'parallel_tool_calls' => true,
        ]);

        $runner = app(AgentRunner::class);
        $agent = $runner->resolveAgent($definition);

        $this->assertInstanceOf(DynamicAgent::class, $agent);

        $ref = new ReflectionObject($agent);
        $max = $ref->getProperty('toolMaxRuns');
        $max->setAccessible(true);
        $parallel = $ref->getProperty('parallelToolCalls');
        $parallel->setAccessible(true);

        $this->assertSame(3, $max->getValue($agent));
        $this->assertTrue($parallel->getValue($agent));
    }

    public function test_config_override_wins_over_definition(): void
    {
        $definition = AgentDefinition::create([
            'name' => 'Override Agent',
            'slug' => 'override-agent',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Test',
            'tools' => [],
            'tool_max_runs' => 8,
            'parallel_tool_calls' => false,
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
            'tool_max_runs' => 2,
            'parallel_tool_calls' => true,
        ]);

        $ref = new ReflectionObject($agent);
        $max = $ref->getProperty('toolMaxRuns');
        $max->setAccessible(true);
        $parallel = $ref->getProperty('parallelToolCalls');
        $parallel->setAccessible(true);

        $this->assertSame(2, $max->getValue($agent));
        $this->assertTrue($parallel->getValue($agent));
    }
}
