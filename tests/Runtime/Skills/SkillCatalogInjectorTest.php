<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Runtime\Skills;

use DigitalElvis\NeuronAIStudio\Models\SkillDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillCatalog;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillCatalogEntry;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillCatalogInjector;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;

class SkillCatalogInjectorTest extends TestCase
{
    public function test_injector_leaves_instructions_unchanged_when_catalog_empty(): void
    {
        $result = app(SkillCatalogInjector::class)->augment('Base prompt.', new SkillCatalog);

        $this->assertSame('Base prompt.', $result);
    }

    public function test_injector_lists_only_attached_skills(): void
    {
        $catalog = new SkillCatalog([
            new SkillCatalogEntry(
                name: 'sales',
                description: 'Sales playbook.',
                definition: new SkillDefinition([
                    'slug' => 'sales',
                    'description' => 'Sales playbook.',
                    'body' => 'Be helpful.',
                ]),
            ),
        ]);

        $result = app(SkillCatalogInjector::class)->augment('Base prompt.', $catalog);

        $this->assertStringContainsString('## Available skills', $result);
        $this->assertStringContainsString('**sales**: Sales playbook.', $result);
        $this->assertStringContainsString('activate_skill', $result);
        $this->assertStringStartsWith('Base prompt.', $result);
    }
}
