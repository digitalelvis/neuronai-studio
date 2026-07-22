<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Http\Livewire\Templates\Index;
use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\GraphValidator;
use DigitalElvis\NeuronAIStudio\Services\TemplateInstaller;
use Livewire\Livewire;

class TemplateInstallerTest extends TestCase
{
    public function test_install_agent_creates_definition(): void
    {
        $agent = app(TemplateInstaller::class)->installAgent('support-assistant');

        $this->assertDatabaseHas('agent_definitions', [
            'id' => $agent->id,
            'slug' => 'support-assistant',
            'name' => 'Support Assistant',
        ]);
    }

    public function test_install_agent_reuses_existing_slug(): void
    {
        $installer = app(TemplateInstaller::class);
        $first = $installer->installAgent('support-assistant');
        $second = $installer->installAgent('support-assistant');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, AgentDefinition::count());
    }

    public function test_install_workflow_creates_agents_and_remaps_agent_ref(): void
    {
        $workflow = app(TemplateInstaller::class)->installWorkflow('basic-agent-chat');

        $this->assertDatabaseHas('workflow_definitions', [
            'id' => $workflow->id,
            'name' => 'Basic Agent Chat',
            'source' => 'studio',
        ]);

        $agent = AgentDefinition::where('slug', 'support-assistant')->first();
        $this->assertNotNull($agent);

        $agentNode = collect($workflow->graph['nodes'] ?? [])
            ->first(fn (array $node) => ($node['type'] ?? '') === 'agent');

        $this->assertNotNull($agentNode);
        $this->assertSame($agent->id, $agentNode['data']['agent_id'] ?? null);
        $this->assertArrayNotHasKey('agent_ref', $agentNode['data'] ?? []);
    }

    public function test_installed_workflow_graph_is_valid(): void
    {
        $workflow = app(TemplateInstaller::class)->installWorkflow('support-rag-hitl');
        $result = app(GraphValidator::class)->validate($workflow->graph);

        $this->assertTrue($result['valid'], implode(' ', $result['errors']));
    }

    public function test_install_rag_knowledge_qna_workflow_is_valid_and_wires_agent(): void
    {
        $workflow = app(TemplateInstaller::class)->installWorkflow('rag-knowledge-qna');

        $result = app(GraphValidator::class)->validate($workflow->graph);
        $this->assertTrue($result['valid'], implode(' ', $result['errors']));

        $nodes = collect($workflow->graph['nodes'] ?? []);

        $ragNode = $nodes->first(fn (array $node) => ($node['type'] ?? '') === 'rag');
        $this->assertNotNull($ragNode);
        $this->assertSame('rag_context', $ragNode['data']['output_key'] ?? null);

        $knowledgeAgent = AgentDefinition::where('slug', 'knowledge-agent')->first();
        $this->assertNotNull($knowledgeAgent);

        $agentNode = $nodes->first(fn (array $node) => ($node['type'] ?? '') === 'agent');
        $this->assertNotNull($agentNode);
        $this->assertSame($knowledgeAgent->id, $agentNode['data']['agent_id'] ?? null);
        $this->assertArrayNotHasKey('agent_ref', $agentNode['data'] ?? []);
        $this->assertStringContainsString('{{ rag_context.context }}', $agentNode['data']['message'] ?? '');
    }

    public function test_install_parallel_support_triage_is_valid_and_wires_fork_join(): void
    {
        $workflow = app(TemplateInstaller::class)->installWorkflow('parallel-support-triage');

        $result = app(GraphValidator::class)->validate($workflow->graph);
        $this->assertTrue($result['valid'], implode(' ', $result['errors']));

        $nodes = collect($workflow->graph['nodes'] ?? []);

        $this->assertNotNull($nodes->first(fn (array $node) => ($node['type'] ?? '') === 'fork'));
        $this->assertNotNull($nodes->first(fn (array $node) => ($node['type'] ?? '') === 'join'));

        $checkpointed = $nodes->filter(fn (array $node) => ($node['data']['checkpoint'] ?? false) === true);
        $this->assertGreaterThanOrEqual(3, $checkpointed->count());

        $composer = AgentDefinition::where('slug', 'support-triage-composer')->first();
        $this->assertNotNull($composer);

        $agentNode = $nodes->first(fn (array $node) => ($node['type'] ?? '') === 'agent');
        $this->assertNotNull($agentNode);
        $this->assertSame($composer->id, $agentNode['data']['agent_id'] ?? null);
        $this->assertArrayNotHasKey('agent_ref', $agentNode['data'] ?? []);
    }

    public function test_install_parallel_triage_hitl_is_valid_with_human_branch(): void
    {
        $workflow = app(TemplateInstaller::class)->installWorkflow('parallel-triage-hitl');

        $result = app(GraphValidator::class)->validate($workflow->graph);
        $this->assertTrue($result['valid'], implode(' ', $result['errors']));

        $nodes = collect($workflow->graph['nodes'] ?? []);
        $this->assertNotNull($nodes->first(fn (array $node) => ($node['type'] ?? '') === 'human'));
        $this->assertNotNull($nodes->first(fn (array $node) => ($node['type'] ?? '') === 'fork'));
    }

    public function test_install_parallel_refund_approval_wires_tool_approval(): void
    {
        $workflow = app(TemplateInstaller::class)->installWorkflow('parallel-refund-approval');

        $result = app(GraphValidator::class)->validate($workflow->graph);
        $this->assertTrue($result['valid'], implode(' ', $result['errors']));

        $nodes = collect($workflow->graph['nodes'] ?? []);

        $this->assertNotNull($nodes->first(fn (array $node) => ($node['type'] ?? '') === 'fork'));
        $this->assertNotNull($nodes->first(fn (array $node) => ($node['type'] ?? '') === 'join'));

        $refundAgent = AgentDefinition::where('slug', 'refund-actions-agent')->first();
        $this->assertNotNull($refundAgent);
        $this->assertTrue((bool) $refundAgent->require_tool_approval);
        $this->assertSame(
            'class:DigitalElvis\\NeuronAIStudio\\Tools\\IssueRefundTool',
            $refundAgent->tools[0]['ref'] ?? null,
        );

        $composer = AgentDefinition::where('slug', 'support-triage-composer')->first();
        $this->assertNotNull($composer);

        $refundNode = $nodes->first(
            fn (array $node) => ($node['type'] ?? '') === 'agent'
                && ($node['data']['agent_id'] ?? null) === $refundAgent->id
        );
        $this->assertNotNull($refundNode);
        $this->assertTrue((bool) ($refundNode['data']['require_tool_approval'] ?? false));
        $this->assertArrayNotHasKey('agent_ref', $refundNode['data'] ?? []);

        $composeNode = $nodes->first(
            fn (array $node) => ($node['type'] ?? '') === 'agent'
                && ($node['data']['agent_id'] ?? null) === $composer->id
        );
        $this->assertNotNull($composeNode);
        $this->assertArrayNotHasKey('agent_ref', $composeNode['data'] ?? []);
    }

    public function test_install_workflow_reuses_existing_agents(): void
    {
        $installer = app(TemplateInstaller::class);
        $installer->installWorkflow('basic-agent-chat');
        $countAfterFirst = AgentDefinition::count();

        $installer->installWorkflow('lead-qualification');

        $this->assertSame($countAfterFirst, AgentDefinition::count());
        $this->assertSame(2, WorkflowDefinition::count());
    }

    public function test_use_template_livewire_action_redirects_to_editor(): void
    {
        Livewire::test(Index::class)
            ->call('useTemplate', 'workflow', 'basic-agent-chat')
            ->assertRedirect();

        $workflow = WorkflowDefinition::where('name', 'Basic Agent Chat')->first();
        $this->assertNotNull($workflow);
    }

    public function test_install_workflow_uses_studio_default_provider_on_llm_nodes(): void
    {
        config([
            'neuronai-studio.default_provider' => 'gemini',
            'neuronai-studio.default_model' => 'gemini-3.5-flash',
        ]);

        $workflow = app(TemplateInstaller::class)->installWorkflow('lead-qualification');

        $llmNodes = collect($workflow->graph['nodes'] ?? [])
            ->filter(fn (array $node) => ($node['type'] ?? '') === 'llm');

        $this->assertCount(2, $llmNodes);

        foreach ($llmNodes as $node) {
            $this->assertSame('gemini', $node['data']['provider'] ?? null);
            $this->assertSame('gemini-3.5-flash', $node['data']['model'] ?? null);
        }
    }

    public function test_install_agent_uses_studio_default_provider(): void
    {
        config([
            'neuronai-studio.default_provider' => 'gemini',
            'neuronai-studio.default_model' => 'gemini-3.5-flash',
        ]);

        $agent = app(TemplateInstaller::class)->installAgent('support-assistant');

        $this->assertSame('gemini', $agent->provider);
        $this->assertSame('gemini-3.5-flash', $agent->model);
    }
}
