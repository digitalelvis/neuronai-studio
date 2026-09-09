<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Connectors;

use DigitalElvis\NeuronAIStudio\Http\Livewire\Connectors\Catalog;
use DigitalElvis\NeuronAIStudio\Http\Livewire\Connectors\Detail;
use DigitalElvis\NeuronAIStudio\Http\Livewire\Connectors\ImportMcpJson;
use DigitalElvis\NeuronAIStudio\Http\Livewire\Plugins\Index;
use DigitalElvis\NeuronAIStudio\Http\Livewire\Plugins\Show;
use DigitalElvis\NeuronAIStudio\Http\Livewire\Skills\Index as SkillsIndex;
use DigitalElvis\NeuronAIStudio\McpServer\McpJsonImporter;
use DigitalElvis\NeuronAIStudio\Models\McpServer;
use DigitalElvis\NeuronAIStudio\Models\PluginInstall;
use DigitalElvis\NeuronAIStudio\Models\SkillDefinition;
use DigitalElvis\NeuronAIStudio\Plugins\PluginInstaller;
use DigitalElvis\NeuronAIStudio\Registry\ConnectorCatalog;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use Livewire\Livewire;

class ConnectorsLayoutTest extends TestCase
{
    public function test_catalog_aggregates_plugins_and_tools(): void
    {
        config(['neuronai-studio.plugins.enabled' => true]);

        $entries = app(ConnectorCatalog::class)->catalogEntries();

        $types = array_column($entries, 'type');

        $this->assertContains('plugin', $types);
        $this->assertContains('tool', $types);
    }

    public function test_catalog_filter_by_type(): void
    {
        config(['neuronai-studio.plugins.enabled' => true]);

        $catalog = app(ConnectorCatalog::class);
        $plugins = $catalog->filter($catalog->catalogEntries(), '', 'plugin');

        $this->assertNotEmpty($plugins);
        $this->assertTrue(collect($plugins)->every(fn (array $entry) => $entry['type'] === 'plugin'));
    }

    public function test_catalog_includes_data_sources(): void
    {
        config(['neuronai-studio.plugins.enabled' => true]);

        $entries = app(ConnectorCatalog::class)->catalogEntries();
        $types = array_column($entries, 'type');

        $this->assertContains('data_source', $types);
        $this->assertTrue(collect($entries)->contains(fn (array $entry) => ($entry['slug'] ?? '') === 'pinecone'));
    }

    public function test_catalog_filter_data_sources(): void
    {
        config(['neuronai-studio.plugins.enabled' => true]);

        $catalog = app(ConnectorCatalog::class);
        $sources = $catalog->filter($catalog->catalogEntries(), '', 'data_source');

        $this->assertNotEmpty($sources);
        $this->assertTrue(collect($sources)->every(fn (array $entry) => $entry['type'] === 'data_source'));
    }

    public function test_plugins_index_renders_catalog_view(): void
    {
        config(['neuronai-studio.plugins.enabled' => true]);

        Livewire::test(Index::class)
            ->assertSee(__('neuronai-studio::connectors.my_connectors'))
            ->assertSee(__('neuronai-studio::connectors.create'));
    }

    public function test_plugins_index_switches_to_installed_view(): void
    {
        config(['neuronai-studio.plugins.enabled' => true]);

        Livewire::test(Index::class)
            ->call('showInstalled')
            ->assertSet('view', 'installed')
            ->call('showCatalog')
            ->assertSet('view', 'catalog');
    }

    public function test_detail_modal_opens_without_page_navigation(): void
    {
        config(['neuronai-studio.plugins.enabled' => true]);

        Livewire::test(Detail::class)
            ->call('open', 'plugin:demo-assistant')
            ->assertSet('ref', 'plugin:demo-assistant')
            ->assertSee('demo-assistant');
    }

    public function test_manage_open_detail_opens_installed_connector(): void
    {
        config(['neuronai-studio.plugins.enabled' => true]);

        $install = app(PluginInstaller::class)->installFromCatalogSlug('demo-assistant');
        $ref = 'plugin_install:'.$install->id;

        Livewire::test(Detail::class)
            ->dispatch('connector-open-detail', ref: $ref)
            ->assertSet('ref', $ref)
            ->assertSee($install->name);
    }

    public function test_mcp_json_importer_creates_servers(): void
    {
        $json = <<<'JSON'
{
  "mcpServers": {
    "demo-json-server": {
      "command": "echo",
      "args": ["hello"]
    }
  }
}
JSON;

        config(['neuronai-studio.mcp_stdio_allowlist' => ['echo']]);

        $created = app(McpJsonImporter::class)->import($json);

        $this->assertCount(1, $created);
        $this->assertDatabaseHas(McpServer::query()->getModel()->getTable(), [
            'slug' => 'demo-json-server',
        ]);
    }

    public function test_import_mcp_json_embedded_dispatches_event(): void
    {
        config(['neuronai-studio.mcp_stdio_allowlist' => ['echo']]);

        Livewire::test(ImportMcpJson::class, ['embedded' => true])
            ->set('json', '{"mcpServers":{"evt-server":{"command":"echo","args":["x"]}}}')
            ->call('import')
            ->assertDispatched('connector-saved');
    }

    public function test_plugin_skill_cannot_be_deleted_while_plugin_installed(): void
    {
        config(['neuronai-studio.plugins.enabled' => true]);

        $install = app(PluginInstaller::class)->installFromCatalogSlug('demo-assistant');
        $skillId = $install->materializedSkillIds()[0];

        Livewire::test(SkillsIndex::class)
            ->call('delete', $skillId)
            ->assertHasNoErrors();

        $this->assertNotNull(SkillDefinition::query()->find($skillId));
    }

    public function test_is_locked_by_plugin_when_install_active(): void
    {
        config(['neuronai-studio.plugins.enabled' => true]);

        $install = app(PluginInstaller::class)->installFromCatalogSlug('demo-assistant');
        $skill = SkillDefinition::query()->find($install->materializedSkillIds()[0]);

        $this->assertNotNull($skill);
        $this->assertTrue($skill->isLockedByPlugin());
    }

    public function test_orphan_plugin_skill_can_be_deleted(): void
    {
        config(['neuronai-studio.plugins.enabled' => true]);

        $install = app(PluginInstaller::class)->installFromCatalogSlug('demo-assistant');
        $skill = SkillDefinition::query()->findOrFail($install->materializedSkillIds()[0]);

        $install->update(['status' => PluginInstall::STATUS_UNINSTALLED]);

        $this->assertFalse($skill->fresh()->isLockedByPlugin());

        Livewire::test(SkillsIndex::class)
            ->call('delete', $skill->id)
            ->assertHasNoErrors();

        $this->assertNull(SkillDefinition::query()->find($skill->id));
    }

    public function test_plugins_show_redirects_to_catalog_deep_link(): void
    {
        config(['neuronai-studio.plugins.enabled' => true]);

        $install = app(PluginInstaller::class)->installFromCatalogSlug('demo-assistant');

        Livewire::test(Show::class, ['install' => $install])
            ->assertRedirect(route('neuronai-studio.plugins.index', [
                'connector' => 'plugin_install:'.$install->id,
            ]));
    }
}
