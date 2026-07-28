<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Http\Middleware\EnsureNeuronAIStudioAuthorized;
use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;

class StudioCreateRoutesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureNeuronAIStudioAuthorized::class);
    }

    public function test_agent_create_page_renders(): void
    {
        $this->get(route('neuronai-studio.agents.create'))
            ->assertOk();
    }

    public function test_tool_create_page_renders(): void
    {
        $this->get(route('neuronai-studio.tools.create'))
            ->assertOk();
    }

    public function test_workflow_create_page_renders(): void
    {
        $this->get(route('neuronai-studio.workflows.create'))
            ->assertOk();
    }

    public function test_knowledge_base_create_page_renders(): void
    {
        $this->get(route('neuronai-studio.knowledge-bases.create'))
            ->assertOk();
    }

    public function test_mcp_server_create_page_renders(): void
    {
        $this->get(route('neuronai-studio.mcp-servers.create'))
            ->assertOk();
    }

    public function test_eval_suite_create_page_renders(): void
    {
        $agent = AgentDefinition::create([
            'name' => 'Eval Host',
            'slug' => 'eval-host',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
        ]);

        $this->get(route('neuronai-studio.agents.evals.create', $agent))
            ->assertOk();
    }
}
