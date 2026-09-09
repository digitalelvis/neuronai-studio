<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Plugins;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\PluginInstall;
use DigitalElvis\NeuronAIStudio\Models\PluginPackage;
use DigitalElvis\NeuronAIStudio\Models\PluginPackageSkill;
use DigitalElvis\NeuronAIStudio\Models\SkillDefinition;
use DigitalElvis\NeuronAIStudio\Plugins\PluginAgentBinder;
use DigitalElvis\NeuronAIStudio\Plugins\PluginInstaller;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillResolver;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\Tools\ActivateSkillTool;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;

class PluginPackageSharingTest extends TestCase
{
    public function test_ensuring_same_pack_twice_reuses_package_skills(): void
    {
        config(['neuronai-studio.plugins.enabled' => true, 'neuronai-studio.plugins.mode' => 'closed']);

        $parsed = app(\DigitalElvis\NeuronAIStudio\Plugins\PluginManifestParser::class)
            ->parseRoot(dirname(__DIR__, 2).'/resources/plugins/demo-assistant');

        $registry = app(\DigitalElvis\NeuronAIStudio\Plugins\PluginPackageRegistry::class);
        $first = $registry->ensureFromParsed($parsed, ['source' => 'catalog']);
        $second = $registry->ensureFromParsed($parsed, ['source' => 'catalog']);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PluginPackage::query()->count());
        $this->assertGreaterThan(0, PluginPackageSkill::query()->where('plugin_package_id', $first->id)->count());
    }

    public function test_catalog_install_does_not_clone_skill_definitions(): void
    {
        config(['neuronai-studio.plugins.enabled' => true, 'neuronai-studio.plugins.mode' => 'closed']);

        $before = SkillDefinition::query()->count();
        $install = app(PluginInstaller::class)->installFromCatalogSlug('demo-assistant');

        $this->assertSame($before, SkillDefinition::query()->count());
        $this->assertNotEmpty($install->materializedSkillRefs());
        $this->assertStringStartsWith('skill:pkg:', $install->materializedSkillRefs()[0]);
        $this->assertEmpty($install->materializedSkillIds());
    }

    public function test_agent_binding_uses_package_skill_refs(): void
    {
        config(['neuronai-studio.plugins.enabled' => true]);

        $install = app(PluginInstaller::class)->installFromCatalogSlug('demo-assistant');
        $agent = AgentDefinition::create([
            'name' => 'Shared Package Agent',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Test',
            'tools' => [],
            'skills' => [],
        ]);

        app(PluginAgentBinder::class)->syncAgent($agent, [
            ['install_id' => $install->id],
        ]);

        $agent->refresh();
        $refs = collect($agent->skills)->pluck('ref')->all();
        $expected = $install->materializedSkillRefs()[0];

        $this->assertContains($expected, $refs);

        $catalog = app(SkillResolver::class)->resolve($agent->skills);
        $this->assertNotNull($catalog->get('demo-assistant'));

        $tool = new ActivateSkillTool($catalog);
        $activated = $tool('demo-assistant');
        $this->assertStringContainsString('Skill: demo-assistant', $activated);
        $this->assertStringNotContainsString('Error:', $activated);
    }

    public function test_uninstall_keeps_shared_package(): void
    {
        config(['neuronai-studio.plugins.enabled' => true]);

        $install = app(PluginInstaller::class)->installFromCatalogSlug('demo-assistant');
        $packageId = $install->package_id;
        $skillCount = PluginPackageSkill::query()->where('plugin_package_id', $packageId)->count();

        app(PluginInstaller::class)->uninstall($install);

        $this->assertSame(PluginInstall::STATUS_UNINSTALLED, $install->fresh()->status);
        $this->assertNotNull(PluginPackage::query()->find($packageId));
        $this->assertSame(
            $skillCount,
            PluginPackageSkill::query()->where('plugin_package_id', $packageId)->count()
        );
    }
}
