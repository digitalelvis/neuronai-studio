<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Codegen;

use DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators\AgentNodeCodeGenerator;
use DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators\CodegenContext;
use DigitalElvis\NeuronAIStudio\Codegen\PhpArrayExporter;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;

class AgentNodeMemoryCodegenTest extends TestCase
{
    public function test_emits_memory_overrides_when_set_on_node(): void
    {
        $generator = new AgentNodeCodeGenerator;
        $context = new CodegenContext(new PhpArrayExporter);

        $result = $generator->generate([
            'data' => [
                'agent_id' => 1,
                'message' => 'hi',
                'output_key' => 'out',
                'context_window' => 2000,
                'driver' => 'in_memory',
                'summarization_enabled' => true,
                'budget_rag' => 800,
                'budget_tool_results' => 1000,
                'budget_state' => 500,
            ],
            'returnType' => 'default',
        ], $context);

        $this->assertStringContainsString("'context_window' => 2000,", $result['body']);
        $this->assertStringContainsString("'driver' => 'in_memory',", $result['body']);
        $this->assertStringContainsString("'summarization_enabled' => true,", $result['body']);
        $this->assertStringContainsString("'budget_rag' => 800,", $result['body']);
        $this->assertStringContainsString("'budget_tool_results' => 1000,", $result['body']);
        $this->assertStringContainsString("'budget_state' => 500,", $result['body']);
        $this->assertStringContainsString("'routing_config' => \$agent->routing_config,", $result['body']);
    }

    public function test_embeds_inline_routing_config_when_enabled(): void
    {
        $generator = new AgentNodeCodeGenerator;
        $context = new CodegenContext(new PhpArrayExporter);

        $result = $generator->generate([
            'data' => [
                'message' => 'hi',
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'instructions' => 'Help.',
                'routing' => [
                    'enabled' => true,
                    'easy_max' => 0.33,
                    'medium_max' => 0.70,
                    'tiers' => [
                        'hard' => ['provider' => 'openai', 'model' => 'o3', 'api_key' => null],
                    ],
                ],
            ],
            'returnType' => 'default',
        ], $context);

        $this->assertStringContainsString("'routing_config' =>", $result['body']);
        $this->assertStringContainsString("'o3'", $result['body']);
        $this->assertStringNotContainsString('$agent->routing_config', $result['body']);
    }

    public function test_emits_runtime_inherit_comment_when_no_override(): void
    {
        $generator = new AgentNodeCodeGenerator;
        $context = new CodegenContext(new PhpArrayExporter);

        $result = $generator->generate([
            'data' => [
                'agent_id' => 1,
                'message' => 'hi',
                'output_key' => 'out',
            ],
            'returnType' => 'default',
        ], $context);

        $this->assertStringContainsString('memory_config: inherited from AgentDefinition at runtime', $result['body']);
    }
}
