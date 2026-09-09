<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Plugins;

use DigitalElvis\NeuronAIStudio\Exceptions\VariableResolutionException;
use DigitalElvis\NeuronAIStudio\Models\McpServer;
use DigitalElvis\NeuronAIStudio\Models\PluginInstall;
use DigitalElvis\NeuronAIStudio\Models\Variable;
use DigitalElvis\NeuronAIStudio\Plugins\PluginAccountService;
use DigitalElvis\NeuronAIStudio\Plugins\PluginInstaller;
use DigitalElvis\NeuronAIStudio\Plugins\PluginManifestParser;
use DigitalElvis\NeuronAIStudio\Registry\ConnectorCatalog;
use DigitalElvis\NeuronAIStudio\Registry\PluginCatalogRegistry;
use DigitalElvis\NeuronAIStudio\Repositories\VariableRepository;
use DigitalElvis\NeuronAIStudio\Tenancy\StudioTenancy;
use DigitalElvis\NeuronAIStudio\Tests\Support\MutableTenantResolver;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;

class PluginOfficialConnectorsTest extends TestCase
{
    /** @var array<int, string> */
    private const OFFICIAL_SLUGS = ['linear', 'stripe', 'hubspot', 'canva', 'mercadopago'];

    protected function tearDown(): void
    {
        MutableTenantResolver::$id = null;
        StudioTenancy::reset();
        parent::tearDown();
    }

    public function test_catalog_lists_official_mcp_packs(): void
    {
        $catalog = app(PluginCatalogRegistry::class);

        foreach (self::OFFICIAL_SLUGS as $slug) {
            $listing = $catalog->find($slug);

            $this->assertNotNull($listing, "Missing catalog listing for [{$slug}]");
            $this->assertDirectoryExists($listing['path']);
            $this->assertNotSame($slug, $listing['title'] ?? $slug);
        }
    }

    public function test_connector_catalog_shows_titles_for_official_packs(): void
    {
        config(['neuronai-studio.plugins.enabled' => true]);

        $entries = app(ConnectorCatalog::class)->catalogEntries();
        $linear = collect($entries)->firstWhere('ref', 'plugin:linear');

        $this->assertNotNull($linear);
        $this->assertSame('Linear', $linear['name']);
    }

    public function test_linear_parser_reads_http_mcp_and_token_env(): void
    {
        $root = dirname(__DIR__, 2).'/resources/plugins/linear';
        $parsed = app(PluginManifestParser::class)->parseRoot($root);

        $this->assertSame('linear', $parsed->slug());
        $this->assertArrayHasKey('linear', $parsed->mcpServers);
        $this->assertSame('https://mcp.linear.app/mcp', $parsed->mcpServers['linear']['url']);
        $this->assertSame('http', $parsed->mcpServers['linear']['transport']);
        $this->assertSame('LINEAR_API_KEY', $parsed->mcpServers['linear']['token_env']);
        $this->assertSame('oauth_or_token', $parsed->mcpServers['linear']['auth']);
    }

    public function test_install_linear_materializes_http_mcp_and_needs_auth(): void
    {
        config([
            'neuronai-studio.plugins.enabled' => true,
            'neuronai-studio.plugins.mode' => 'closed',
            'neuronai-studio.plugins.stdio' => false,
        ]);

        $install = app(PluginInstaller::class)->installFromCatalogSlug('linear');

        $this->assertSame('linear', $install->slug);
        $this->assertNotEmpty($install->materializedMcpSlugs());

        $mcp = McpServer::query()->where('slug', 'linear-linear')->first();
        $this->assertNotNull($mcp);
        $this->assertSame('http', $mcp->transport);
        $this->assertSame('https://mcp.linear.app/mcp', $mcp->url);
        $this->assertSame('var:LINEAR_LINEAR_API_KEY', $mcp->token_env);
        $this->assertSame('oauth_or_token', $mcp->metadata['auth'] ?? null);

        $account = $install->accounts()->first();
        $this->assertNotNull($account);
        $this->assertSame('needs_auth', $account->auth_status);
    }

    public function test_tenants_install_same_pack_independently(): void
    {
        config([
            'neuronai-studio.plugins.enabled' => true,
            'neuronai-studio.plugins.mode' => 'closed',
        ]);

        $this->enableTenancy('acme');
        $installA = app(PluginInstaller::class)->installFromCatalogSlug('linear');

        $this->enableTenancy('beta');
        $installB = app(PluginInstaller::class)->installFromCatalogSlug('linear');

        $this->assertNotSame($installA->id, $installB->id);
        $this->assertSame('acme', $installA->tenant_id);
        $this->assertSame('beta', $installB->tenant_id);

        $this->enableTenancy('acme');
        $this->assertSame(1, PluginInstall::query()->inCurrentTenant()->where('slug', 'linear')->count());

        [$mcpA, $mcpB] = StudioTenancy::withoutScope(function () {
            return [
                McpServer::query()->where('slug', 'linear-linear')->where('tenant_id', 'acme')->first(),
                McpServer::query()->where('slug', 'linear-linear')->where('tenant_id', 'beta')->first(),
            ];
        });

        $this->assertNotNull($mcpA);
        $this->assertNotNull($mcpB);
        $this->assertNotSame($mcpA->id, $mcpB->id);
    }

    public function test_tenant_credentials_do_not_leak_across_tenants(): void
    {
        config([
            'neuronai-studio.plugins.enabled' => true,
            'neuronai-studio.plugins.mode' => 'closed',
        ]);

        $this->enableTenancy('acme');
        $installA = app(PluginInstaller::class)->installFromCatalogSlug('linear');
        $accountA = $installA->accounts()->first();
        $this->assertNotNull($accountA);

        Variable::create([
            'name' => 'LINEAR_LINEAR_API_KEY',
            'type' => Variable::TYPE_CREDENTIAL,
            'value' => 'acme-secret-key',
        ]);

        app(PluginAccountService::class)->refreshAuthStatus($accountA->fresh());
        $this->assertSame('connected', $accountA->fresh()->auth_status);

        $this->enableTenancy('beta');
        $installB = app(PluginInstaller::class)->installFromCatalogSlug('linear');
        $accountB = $installB->accounts()->first();
        $this->assertNotNull($accountB);
        $this->assertSame('needs_auth', $accountB->auth_status);

        $this->expectException(VariableResolutionException::class);
        app(VariableRepository::class)->resolveValue('LINEAR_LINEAR_API_KEY');
    }

    public function test_disconnect_clears_vault_credentials_and_marks_needs_auth(): void
    {
        config([
            'neuronai-studio.plugins.enabled' => true,
            'neuronai-studio.plugins.mode' => 'closed',
        ]);

        $install = app(PluginInstaller::class)->installFromCatalogSlug('linear');
        $account = $install->accounts()->first();
        $this->assertNotNull($account);

        Variable::create([
            'name' => 'LINEAR_LINEAR_API_KEY',
            'type' => Variable::TYPE_CREDENTIAL,
            'value' => 'secret-key',
        ]);

        app(PluginAccountService::class)->refreshAuthStatus($account->fresh());
        $this->assertSame('connected', $account->fresh()->auth_status);

        app(PluginAccountService::class)->disconnect($account->fresh());

        $account = $account->fresh();
        $this->assertSame('needs_auth', $account->auth_status);

        $variable = Variable::query()->where('name', 'LINEAR_LINEAR_API_KEY')->first();
        $this->assertNotNull($variable);
        $this->assertSame('', $variable->value);

        $this->expectException(VariableResolutionException::class);
        app(VariableRepository::class)->resolveValue('LINEAR_LINEAR_API_KEY');
    }

    public function test_tenant_install_does_not_mutate_global_install(): void
    {
        config([
            'neuronai-studio.plugins.enabled' => true,
            'neuronai-studio.plugins.mode' => 'closed',
        ]);

        $global = StudioTenancy::central(fn () => app(PluginInstaller::class)->installFromCatalogSlug('demo-assistant'));
        $globalName = $global->name;

        $this->enableTenancy('acme');
        app(PluginInstaller::class)->installFromCatalogSlug('linear');

        $globalFresh = StudioTenancy::withoutScope(fn () => PluginInstall::query()->find($global->id));
        $this->assertNotNull($globalFresh);
        $this->assertSame($globalName, $globalFresh->name);
        $this->assertNull($globalFresh->tenant_id);
    }

    protected function enableTenancy(?string $tenantId, string $driver = 'shared'): void
    {
        config([
            'neuronai-studio.tenancy.enabled' => true,
            'neuronai-studio.tenancy.driver' => $driver,
            'neuronai-studio.tenancy.resolver' => MutableTenantResolver::class,
        ]);
        MutableTenantResolver::$id = $tenantId;
        StudioTenancy::reset();
    }
}
