<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Registry\TemplateRegistry;

class TemplateRegistryTest extends TestCase
{
    public function test_registry_lists_all_bundled_templates(): void
    {
        $registry = app(TemplateRegistry::class);
        $templates = $registry->all();

        $this->assertCount(11, $templates);

        $ids = collect($templates)->map(fn (array $entry) => $entry['type'].':'.$entry['id'])->sort()->values()->all();

        $this->assertSame([
            'agent:eval-judge-correctness',
            'agent:eval-judge-faithfulness',
            'agent:eval-judge-general',
            'agent:eval-judge-helpfulness',
            'agent:eval-judge-relevance',
            'agent:intent-classifier',
            'agent:knowledge-agent',
            'agent:support-assistant',
            'workflow:basic-agent-chat',
            'workflow:lead-qualification',
            'workflow:support-rag-hitl',
        ], $ids);
    }

    public function test_registry_filters_by_type_and_complexity(): void
    {
        $registry = app(TemplateRegistry::class);

        $this->assertCount(8, $registry->all('agent'));
        $this->assertCount(3, $registry->all('workflow'));
        $this->assertCount(1, $registry->all('workflow', 'basic'));
        $this->assertCount(1, $registry->all('workflow', 'intermediate'));
        $this->assertCount(1, $registry->all('workflow', 'advanced'));
    }

    public function test_registry_loads_agent_template(): void
    {
        $template = app(TemplateRegistry::class)->load('agent', 'support-assistant');

        $this->assertNotNull($template);
        $this->assertSame('support-assistant', $template['meta']['id']);
        $this->assertSame('Support Assistant', $template['meta']['name']);
        $this->assertIsArray($template['definition']);
    }
}
