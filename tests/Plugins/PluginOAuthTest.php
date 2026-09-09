<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Plugins;

use DigitalElvis\NeuronAIStudio\Http\Livewire\Connectors\Detail;
use DigitalElvis\NeuronAIStudio\Http\Middleware\EnsureNeuronAIStudioAuthorized;
use DigitalElvis\NeuronAIStudio\Models\PluginAccount;
use DigitalElvis\NeuronAIStudio\Models\Variable;
use DigitalElvis\NeuronAIStudio\Plugins\OAuth\PluginOAuthRegistry;
use DigitalElvis\NeuronAIStudio\Plugins\OAuth\PluginOAuthService;
use DigitalElvis\NeuronAIStudio\Plugins\PluginInstaller;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

class PluginOAuthTest extends TestCase
{
    public function test_registry_reports_configured_provider(): void
    {
        config([
            'neuronai-studio.plugins.oauth.providers.hubspot.client_id' => 'hub-client',
        ]);

        $registry = app(PluginOAuthRegistry::class);

        $this->assertTrue($registry->isConfigured('hubspot'));
        $this->assertFalse($registry->isConfigured('linear'));
    }

    public function test_oauth_callback_persists_access_token_and_connects_account(): void
    {
        config([
            'neuronai-studio.plugins.enabled' => true,
            'neuronai-studio.plugins.oauth.providers.hubspot.client_id' => 'hub-client',
            'neuronai-studio.plugins.oauth.providers.hubspot.client_secret' => 'hub-secret',
        ]);

        Http::fake([
            'api.hubapi.com/oauth/v1/token' => Http::response([
                'access_token' => 'hub-access-token',
                'refresh_token' => 'hub-refresh-token',
            ], 200),
        ]);

        $install = app(PluginInstaller::class)->installFromCatalogSlug('hubspot');
        $account = $install->accounts()->first();
        $this->assertNotNull($account);
        $this->assertSame(PluginAccount::AUTH_NEEDS, $account->auth_status);

        $oauth = app(PluginOAuthService::class);
        $authorizationUrl = $oauth->authorizationUrl($install, $account);
        parse_str((string) parse_url($authorizationUrl, PHP_URL_QUERY), $query);

        $this->assertStringContainsString('app.hubspot.com/oauth/authorize', $authorizationUrl);
        $this->assertArrayHasKey('state', $query);
        $this->assertArrayHasKey('code_challenge', $query);

        $oauth->handleCallback((string) $query['state'], 'auth-code');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.hubapi.com/oauth/v1/token'
                && $request['grant_type'] === 'authorization_code'
                && $request['code'] === 'auth-code'
                && $request['code_verifier'] !== '';
        });

        $account = $account->fresh();
        $this->assertSame(PluginAccount::AUTH_CONNECTED, $account->auth_status);

        $variable = Variable::query()->where('name', 'HUBSPOT_HUBSPOT_ACCESS_TOKEN')->first();
        $this->assertNotNull($variable);
        $this->assertSame('hub-access-token', $variable->value);
    }

    public function test_oauth_authorize_redirects_to_provider_when_configured(): void
    {
        config([
            'neuronai-studio.plugins.enabled' => true,
            'neuronai-studio.plugins.oauth.providers.hubspot.client_id' => 'hub-client',
        ]);

        $install = app(PluginInstaller::class)->installFromCatalogSlug('hubspot');
        $account = $install->accounts()->first();
        $this->assertNotNull($account);

        $this->withoutMiddleware(EnsureNeuronAIStudioAuthorized::class);

        $response = $this->get(route('neuronai-studio.plugins.oauth.authorize', [
            'slug' => 'hubspot',
            'install' => $install->id,
            'account' => $account->id,
        ]));

        $response->assertRedirect();
        $this->assertStringContainsString('app.hubspot.com/oauth/authorize', (string) $response->headers->get('Location'));
    }

    public function test_oauth_callback_route_redirects_back_to_plugin_detail(): void
    {
        config([
            'neuronai-studio.plugins.enabled' => true,
            'neuronai-studio.plugins.oauth.providers.hubspot.client_id' => 'hub-client',
            'neuronai-studio.plugins.oauth.providers.hubspot.client_secret' => 'hub-secret',
        ]);

        Http::fake([
            'api.hubapi.com/oauth/v1/token' => Http::response(['access_token' => 'hub-access-token'], 200),
        ]);

        $install = app(PluginInstaller::class)->installFromCatalogSlug('hubspot');
        $account = $install->accounts()->first();
        $this->assertNotNull($account);

        $oauth = app(PluginOAuthService::class);
        $authorizationUrl = $oauth->authorizationUrl($install, $account);
        parse_str((string) parse_url($authorizationUrl, PHP_URL_QUERY), $query);

        $this->withoutMiddleware(EnsureNeuronAIStudioAuthorized::class);

        $response = $this->get(route('neuronai-studio.plugins.oauth.callback', [
            'state' => $query['state'],
            'code' => 'auth-code',
        ]));

        $response->assertRedirect(route('neuronai-studio.plugins.index', [
            'connector' => 'plugin_install:'.$install->id,
        ]));
        $response->assertSessionHas('success');
    }

    public function test_canva_oauth_uses_mcp_endpoints(): void
    {
        config([
            'neuronai-studio.plugins.enabled' => true,
            'neuronai-studio.plugins.oauth.providers.canva.client_id' => 'mcp-canva-client',
            'neuronai-studio.plugins.oauth.providers.canva.client_secret' => 'canva-secret',
        ]);

        Http::fake([
            'mcp.canva.com/token' => Http::response([
                'access_token' => 'canva-mcp-access-token',
                'refresh_token' => 'canva-mcp-refresh-token',
            ], 200),
        ]);

        $install = app(PluginInstaller::class)->installFromCatalogSlug('canva');
        $account = $install->accounts()->first();
        $this->assertNotNull($account);

        $oauth = app(PluginOAuthService::class);
        $authorizationUrl = $oauth->authorizationUrl($install, $account);
        parse_str((string) parse_url($authorizationUrl, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith('https://mcp.canva.com/authorize?', $authorizationUrl);
        $this->assertArrayNotHasKey('scope', $query);

        $oauth->handleCallback((string) $query['state'], 'canva-auth-code');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://mcp.canva.com/token'
                && $request['grant_type'] === 'authorization_code'
                && $request['code'] === 'canva-auth-code'
                && $request['client_id'] === 'mcp-canva-client';
        });

        $this->assertSame(PluginAccount::AUTH_CONNECTED, $account->fresh()->auth_status);
    }

    public function test_refresh_access_token_updates_vault_and_expiry(): void
    {
        config([
            'neuronai-studio.plugins.enabled' => true,
            'neuronai-studio.plugins.oauth.providers.canva.client_id' => 'mcp-canva-client',
            'neuronai-studio.plugins.oauth.providers.canva.client_secret' => 'canva-secret',
        ]);

        Http::fake([
            'mcp.canva.com/token' => Http::sequence()
                ->push([
                    'access_token' => 'initial-access',
                    'refresh_token' => 'initial-refresh',
                    'expires_in' => 3600,
                ], 200)
                ->push([
                    'access_token' => 'refreshed-access',
                    'refresh_token' => 'rotated-refresh',
                    'expires_in' => 7200,
                ], 200),
        ]);

        $install = app(PluginInstaller::class)->installFromCatalogSlug('canva');
        $account = $install->accounts()->first();
        $this->assertNotNull($account);

        $oauth = app(PluginOAuthService::class);
        $authorizationUrl = $oauth->authorizationUrl($install, $account);
        parse_str((string) parse_url($authorizationUrl, PHP_URL_QUERY), $query);
        $oauth->handleCallback((string) $query['state'], 'auth-code');

        $account = $account->fresh();
        $this->assertNotNull($account->token_expires_at);
        $account->update(['token_expires_at' => now()->subMinute()]);

        $oauth->ensureFreshAccessToken($account->fresh());

        $account = $account->fresh();
        $this->assertSame(PluginAccount::AUTH_CONNECTED, $account->auth_status);
        $this->assertTrue($account->token_expires_at?->greaterThan(now()->addHour()) ?? false);

        $access = Variable::query()->where('name', 'CANVA_CANVA_ACCESS_TOKEN')->first();
        $refresh = Variable::query()->where('name', 'CANVA_CANVA_REFRESH_TOKEN')->first();
        $this->assertNotNull($access);
        $this->assertNotNull($refresh);
        $this->assertSame('refreshed-access', $access->value);
        $this->assertSame('rotated-refresh', $refresh->value);
    }

    public function test_failed_refresh_disconnects_account(): void
    {
        config([
            'neuronai-studio.plugins.enabled' => true,
            'neuronai-studio.plugins.oauth.providers.canva.client_id' => 'mcp-canva-client',
            'neuronai-studio.plugins.oauth.providers.canva.client_secret' => 'canva-secret',
        ]);

        Http::fake([
            'mcp.canva.com/token' => Http::sequence()
                ->push([
                    'access_token' => 'initial-access',
                    'refresh_token' => 'initial-refresh',
                    'expires_in' => 10,
                ], 200)
                ->push(['error' => 'invalid_grant'], 400),
        ]);

        $install = app(PluginInstaller::class)->installFromCatalogSlug('canva');
        $account = $install->accounts()->first();
        $this->assertNotNull($account);

        $oauth = app(PluginOAuthService::class);
        $authorizationUrl = $oauth->authorizationUrl($install, $account);
        parse_str((string) parse_url($authorizationUrl, PHP_URL_QUERY), $query);
        $oauth->handleCallback((string) $query['state'], 'auth-code');

        $account = $account->fresh();
        $account->update(['token_expires_at' => now()->subMinute()]);

        $oauth->ensureFreshAccessToken($account->fresh());

        $this->assertSame(PluginAccount::AUTH_NEEDS, $account->fresh()->auth_status);
        $this->assertNull($account->fresh()->token_expires_at);
    }

    public function test_canva_oauth_rejects_connect_api_client_id(): void
    {
        config([
            'neuronai-studio.plugins.enabled' => true,
            'neuronai-studio.plugins.oauth.providers.canva.client_id' => 'OC-AaCF0dB5SEVD',
        ]);

        $install = app(PluginInstaller::class)->installFromCatalogSlug('canva');
        $account = $install->accounts()->first();
        $this->assertNotNull($account);

        $this->withoutMiddleware(EnsureNeuronAIStudioAuthorized::class);

        $response = $this->get(route('neuronai-studio.plugins.oauth.authorize', [
            'slug' => 'canva',
            'install' => $install->id,
            'account' => $account->id,
        ]));

        $response->assertRedirect(route('neuronai-studio.plugins.index', [
            'connector' => 'plugin_install:'.$install->id,
        ]));
        $response->assertSessionHas('error');
    }

    public function test_detail_modal_shows_install_and_authenticate_actions(): void
    {
        config([
            'neuronai-studio.plugins.enabled' => true,
            'neuronai-studio.plugins.oauth.providers.linear.client_id' => 'linear-client',
        ]);

        Livewire::test(Detail::class)
            ->call('open', 'plugin:linear')
            ->assertSee(__('neuronai-studio::plugins.install'));

        $install = app(PluginInstaller::class)->installFromCatalogSlug('linear');

        Livewire::test(Detail::class)
            ->call('open', 'plugin_install:'.$install->id)
            ->assertSee(__('neuronai-studio::plugins.needs_auth'))
            ->assertSee(__('neuronai-studio::plugins.authenticate'))
            ->assertSee(__('neuronai-studio::plugins.save_credentials'));
    }
}
