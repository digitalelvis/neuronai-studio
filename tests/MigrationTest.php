<?php

namespace ElvisLopesDigital\NeuronAIStudio\Tests;

use ElvisLopesDigital\NeuronAIStudio\Models\AgentDefinition;
use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowDefinition;
use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowRun;

class MigrationTest extends TestCase
{
    public function test_migrations_create_tables(): void
    {
        $this->assertTrue(\Schema::hasTable('agent_definitions'));
        $this->assertTrue(\Schema::hasTable('workflow_definitions'));
        $this->assertTrue(\Schema::hasTable('workflow_runs'));
        $this->assertTrue(\Schema::hasTable('workflow_run_steps'));
    }

    public function test_models_can_persist_records(): void
    {
        $agent = AgentDefinition::create([
            'name' => 'Test Agent',
            'slug' => 'test-agent',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Test',
        ]);

        $workflow = WorkflowDefinition::create([
            'name' => 'Test Workflow',
            'slug' => 'test-workflow',
            'graph' => WorkflowDefinition::defaultGraph(),
        ]);

        $run = WorkflowRun::create([
            'workflow_definition_id' => $workflow->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('agent_definitions', ['id' => $agent->id]);
        $this->assertDatabaseHas('workflow_definitions', ['id' => $workflow->id]);
        $this->assertDatabaseHas('workflow_runs', ['id' => $run->id]);
    }
}
