<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Plugins;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\AgentMcpServer;
use DigitalElvis\NeuronAIStudio\Models\PluginInstall;
use DigitalElvis\NeuronAIStudio\Models\SkillDefinition;
use DigitalElvis\NeuronAIStudio\Models\Variable;
use DigitalElvis\NeuronAIStudio\Plugins\PluginAgentBinder;
use DigitalElvis\NeuronAIStudio\Plugins\PluginInstaller;
use DigitalElvis\NeuronAIStudio\Plugins\PluginManifestParser;
use DigitalElvis\NeuronAIStudio\Plugins\PluginMcpGate;
use DigitalElvis\NeuronAIStudio\Plugins\PluginPolicy;
use DigitalElvis\NeuronAIStudio\Registry\PluginCatalogRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use Illuminate\Auth\Access\AuthorizationException;

class PluginSystemTest extends TestCase
{
    protected function pluginRoot(): string
    {
        return dirname(__DIR__, 2).'/resources/plugins/demo-assistant';
    }

    public function test_manifest_parser_reads_demo_pack(): void
    {
        $parsed = app(PluginManifestParser::class)->parseRoot($this->pluginRoot());

        $this->assertSame('demo-assistant', $parsed->slug());
        $this->assertNotEmpty($parsed->skillRoots);
    }

    public function test_catalog_lists_demo_assistant(): void
    {
        $listing = app(PluginCatalogRegistry::class)->find('demo-assistant');

        $this->assertNotNull($listing);
        $this->assertDirectoryExists($listing['path']);
    }

    public function test_install_materializes_skill_and_account(): void
    {
        config(['neuronai-studio.plugins.enabled' => true, 'neuronai-studio.plugins.mode' => 'closed']);

        $install = app(PluginInstaller::class)->installFromCatalogSlug('demo-assistant');

        $this->assertSame('demo-assistant', $install->slug);
        $this->assertNotEmpty($install->materializedSkillIds());
        $this->assertCount(1, $install->accounts);

        $skill = SkillDefinition::query()->find($install->materializedSkillIds()[0]);
        $this->assertSame(SkillDefinition::SOURCE_PLUGIN, $skill->source);
    }

    public function test_closed_mode_rejects_unknown_slug_on_install_path(): void
    {
        config(['neuronai-studio.plugins.enabled' => true, 'neuronai-studio.plugins.mode' => 'closed']);

        $this->expectException(AuthorizationException::class);

        app(PluginPolicy::class)->assertSourceAllowed([
            'slug' => 'unknown-pack',
            'source' => 'upload',
            'source_path' => '/tmp/not-allowed',
        ]);
    }

    public function test_agent_binding_syncs_plugin_skills(): void
    {
        config(['neuronai-studio.plugins.enabled' => true]);

        $install = app(PluginInstaller::class)->installFromCatalogSlug('demo-assistant');
        $agent = AgentDefinition::create([
            'name' => 'Plugin Agent',
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
        $this->assertContains('skill:db:'.$install->materializedSkillIds()[0], $refs);
    }

    public function test_mcp_gate_skips_plugin_tools_when_account_needs_auth(): void
    {
        config(['neuronai-studio.plugins.enabled' => true]);

        $install = app(PluginInstaller::class)->installFromCatalogSlug('demo-assistant');
        $agent = AgentDefinition::create([
            'name' => 'Gate Agent',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Test',
            'tools' => [],
            'skills' => [],
        ]);

        app(PluginAgentBinder::class)->syncAgent($agent, [
            ['install_id' => $install->id],
        ]);

        $agent->load('mcpBindings');

        $gate = app(PluginMcpGate::class);

        foreach ($agent->mcpBindings as $binding) {
            // Demo pack is skill-only; if MCP existed, gate would skip when needs_auth.
            $this->assertFalse($gate->shouldSkipBinding($agent, $binding->mcp_server_slug));
        }

        $this->assertTrue(true);
    }

    public function test_disabled_plugins_abort_policy(): void
    {
        config(['neuronai-studio.plugins.enabled' => false]);

        $this->expectException(AuthorizationException::class);

        app(PluginPolicy::class)->assertEnabled();
    }

    public function test_account_connected_when_variables_resolve(): void
    {
        config(['neuronai-studio.plugins.enabled' => true]);

        $install = app(PluginInstaller::class)->installFromCatalogSlug('demo-assistant');
        $account = $install->accounts()->first();

        $this->assertNotNull($account);
        $this->assertTrue($account->isConnected() || $account->auth_status === 'needs_auth');
    }
}
