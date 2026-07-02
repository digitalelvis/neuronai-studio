<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Registry\TemplateRegistry;

class TemplateRegistryTest extends TestCase
{
    public function test_registry_lists_all_bundled_templates(): void
    {
        $registry = app(TemplateRegistry::class);
        $templates = $registry->all();

        $this->assertCount(15, $templates);

        $ids = collect($templates)->map(fn (array $entry) => $entry['type'].':'.$entry['id'])->sort()->values()->all();

        $this->assertSame([
            'agent:eval-judge-correctness',
            'agent:eval-judge-faithfulness',
            'agent:eval-judge-general',
            'agent:eval-judge-helpfulness',
            'agent:eval-judge-relevance',
            'agent:intent-classifier',
            'agent:knowledge-agent',
            'agent:lead-qualifier',
            'agent:support-assistant',
            'workflow:autonomous-lead-qualification',
            'workflow:basic-agent-chat',
            'workflow:lead-qualification',
            'workflow:lead-qualification-loop',
            'workflow:rag-knowledge-qna',
            'workflow:support-rag-hitl',
        ], $ids);
    }

    public function test_registry_filters_by_type_and_complexity(): void
    {
        $registry = app(TemplateRegistry::class);

        $this->assertCount(9, $registry->all('agent'));
        $this->assertCount(6, $registry->all('workflow'));
        $this->assertCount(1, $registry->all('workflow', 'basic'));
        $this->assertCount(4, $registry->all('workflow', 'intermediate'));
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

    public function test_builtin_templates_load_when_config_paths_point_elsewhere(): void
    {
        config()->set('neuronai-studio.template_paths', [
            'agent' => sys_get_temp_dir().'/nonexistent-agents',
            'workflow' => sys_get_temp_dir().'/nonexistent-workflows',
        ]);

        $registry = app(TemplateRegistry::class);

        $this->assertCount(15, $registry->all());
        $this->assertNotNull($registry->load('agent', 'support-assistant'));
        $this->assertNotNull($registry->load('workflow', 'basic-agent-chat'));
    }

    public function test_config_template_paths_are_merged_with_builtins(): void
    {
        $customDir = sys_get_temp_dir().'/neuronai-studio-custom-agent-templates';

        if (! is_dir($customDir)) {
            mkdir($customDir, 0777, true);
        }

        $customPath = $customDir.'/custom-agent.json';
        file_put_contents($customPath, json_encode([
            'meta' => [
                'id' => 'custom-test-agent',
                'name' => 'Custom Test Agent',
                'description' => 'From app config path',
                'category' => 'test',
                'tags' => [],
            ],
            'definition' => [
                'instructions' => 'You are a test agent.',
                'tools' => [],
            ],
        ], JSON_THROW_ON_ERROR));

        config()->set('neuronai-studio.template_paths.agent', $customDir);

        $registry = app(TemplateRegistry::class);
        $ids = collect($registry->all('agent'))->pluck('id')->all();

        $this->assertContains('custom-test-agent', $ids);
        $this->assertContains('support-assistant', $ids);

        @unlink($customPath);
        @rmdir($customDir);
    }
}
