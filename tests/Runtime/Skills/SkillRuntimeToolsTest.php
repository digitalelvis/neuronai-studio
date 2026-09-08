<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Runtime\Skills;

use DigitalElvis\NeuronAIStudio\Models\SkillDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillCatalog;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillCatalogEntry;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\Tools\ActivateSkillTool;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\Tools\ReadSkillResourceTool;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\Tools\RunSkillScriptTool;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use NeuronAI\Providers\Gemini\ToolMapper;

class SkillRuntimeToolsTest extends TestCase
{
    protected function catalog(): SkillCatalog
    {
        return new SkillCatalog([
            new SkillCatalogEntry(
                name: 'debugging',
                description: 'Debug Neuron agents.',
                definition: new SkillDefinition([
                    'slug' => 'debugging',
                    'description' => 'Debug Neuron agents.',
                    'body' => 'Inspect traces first.',
                    'resources' => [
                        'references/checklist.md' => '# Checklist',
                    ],
                ]),
            ),
        ]);
    }

    public function test_activate_skill_returns_body_for_attached_skill(): void
    {
        $tool = new ActivateSkillTool($this->catalog());

        $result = $tool('debugging');

        $this->assertStringContainsString('# Skill: debugging', $result);
        $this->assertStringContainsString('Inspect traces first.', $result);
        $this->assertStringContainsString('references/checklist.md', $result);
    }

    public function test_activate_skill_rejects_unattached_skill(): void
    {
        $tool = new ActivateSkillTool($this->catalog());

        $result = $tool('missing');

        $this->assertStringContainsString('not attached', $result);
        $this->assertStringContainsString('debugging', $result);
    }

    public function test_read_skill_resource_returns_reference_file(): void
    {
        $tool = new ReadSkillResourceTool($this->catalog());

        $result = $tool('debugging', 'references/checklist.md');

        $this->assertSame('# Checklist', $result);
    }

    public function test_read_skill_resource_blocks_path_traversal_and_unknown_prefixes(): void
    {
        $tool = new ReadSkillResourceTool($this->catalog());

        $this->assertStringContainsString('not allowed', $tool('debugging', '../secret.txt'));
        $this->assertStringContainsString('not allowed', $tool('debugging', 'tmp/secret.txt'));
        $this->assertStringContainsString('not found', $tool('debugging', 'scripts/missing.sh'));
        $this->assertStringContainsString('not found', $tool('debugging', 'assets/missing.png'));
    }

    public function test_run_skill_script_args_schema_includes_items_for_gemini(): void
    {
        $tool = new RunSkillScriptTool($this->catalog());
        $args = null;

        foreach ($tool->getProperties() as $property) {
            if ($property->getName() === 'args') {
                $args = $property->getJsonSchema();
                break;
            }
        }

        $this->assertIsArray($args);
        $this->assertSame('array', $args['type']);
        $this->assertSame('string', $args['items']['type'] ?? null);

        $mapped = (new ToolMapper())->map([$tool]);
        $this->assertArrayHasKey('items', $mapped['functionDeclarations'][0]['parameters']['properties']['args']);
    }
}
