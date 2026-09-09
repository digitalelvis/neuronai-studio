<?php

namespace DigitalElvis\NeuronAIStudio\Plugins\OAuth;

use DigitalElvis\NeuronAIStudio\Models\PluginAccount;
use DigitalElvis\NeuronAIStudio\Models\PluginInstall;
use DigitalElvis\NeuronAIStudio\Models\Variable;
use DigitalElvis\NeuronAIStudio\Plugins\PluginAccountService;
use DigitalElvis\NeuronAIStudio\Tenancy\StudioTenancy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class PluginOAuthService
{
    public function __construct(
        protected PluginOAuthRegistry $registry,
        protected PluginAccountService $accounts,
    ) {}

    public function authorizationUrl(PluginInstall $install, PluginAccount $account): string
    {
        $provider = $this->requireProvider((string) $install->slug);

        $state = Str::random(40);
        $codeVerifier = $this->generateCodeVerifier();
        $payload = [
            'plugin_slug' => $install->slug,
            'install_id' => $install->id,
            'account_id' => $account->id,
            'tenant_id' => (string) ($install->tenant_id ?? ''),
            'code_verifier' => $codeVerifier,
            'access_token_env' => (string) ($provider['access_token_env'] ?? ''),
            'refresh_token_env' => (string) ($provider['refresh_token_env'] ?? ''),
        ];

        Cache::put($this->stateCacheKey($state), $payload, now()->addMinutes(10));

        $query = [
            'client_id' => (string) $provider['client_id'],
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'state' => $state,
        ];

        $scopes = $provider['scopes'] ?? [];

        if (is_array($scopes) && $scopes !== []) {
            $query['scope'] = implode(' ', $scopes);
        }

        if (($provider['pkce'] ?? false) === true) {
            $query['code_challenge'] = $this->codeChallenge($codeVerifier);
            $query['code_challenge_method'] = (string) ($provider['code_challenge_method'] ?? 'S256');
        }

        $separator = str_contains((string) $provider['authorization_url'], '?') ? '&' : '?';

        return (string) $provider['authorization_url'].$separator.http_build_query($query);
    }

    public function handleCallback(string $state, string $code): PluginInstall
    {
        $cacheKey = $this->stateCacheKey($state);
        $payload = Cache::pull($cacheKey);

        if (! is_array($payload)) {
            throw new RuntimeException('OAuth state expired or invalid.');
        }

        $install = PluginInstall::query()->find($payload['install_id'] ?? null);
        $account = PluginAccount::query()->find($payload['account_id'] ?? null);

        if ($install === null || $account === null || $account->plugin_install_id !== $install->id) {
            throw new RuntimeException('OAuth session could not be matched to a plugin account.');
        }

        $sessionTenant = (string) ($payload['tenant_id'] ?? '');
        $currentTenant = (string) (StudioTenancy::id() ?? '');

        if ($sessionTenant !== '' && $currentTenant !== '' && $sessionTenant !== $currentTenant) {
            throw new RuntimeException('OAuth session tenant mismatch.');
        }

        $provider = $this->requireProvider((string) $install->slug);
        $tokens = $this->exchangeCode($provider, $code, (string) ($payload['code_verifier'] ?? ''));

        $this->persistTokens($account, $provider, $tokens, $payload);
        $this->persistTokenExpiry($account, $tokens);

        $this->accounts->refreshAuthStatus($account->fresh());

        return $install->fresh(['accounts']);
    }

    public function ensureFreshAccessToken(PluginAccount $account): PluginAccount
    {
        $install = $account->install;

        if ($install === null || ! $this->registry->isConfigured((string) $install->slug)) {
            return $account;
        }

        $provider = $this->registry->forSlug((string) $install->slug);

        if ($provider === null || empty($provider['refresh_token_env'])) {
            return $account;
        }

        if ($account->token_expires_at !== null && $account->token_expires_at->greaterThan(now()->addSeconds(60))) {
            return $account;
        }

        if (! $this->hasRefreshToken($account, $provider)) {
            return $account;
        }

        try {
            return $this->refreshAccessToken($account);
        } catch (RuntimeException) {
            $this->accounts->disconnect($account->fresh());

            return $account->fresh();
        }
    }

    public function refreshAccessToken(PluginAccount $account): PluginAccount
    {
        $install = $account->install;

        if ($install === null) {
            throw new RuntimeException('Plugin account is missing its install.');
        }

        $provider = $this->requireProvider((string) $install->slug);
        $refreshEnv = (string) ($provider['refresh_token_env'] ?? '');

        if ($refreshEnv === '') {
            throw new RuntimeException("OAuth provider [{$install->slug}] does not support refresh tokens.");
        }

        $credentialMap = is_array($account->credential_map) ? $account->credential_map : [];
        $refreshToken = $this->resolveStoredCredential($credentialMap, $refreshEnv);

        if ($refreshToken === null) {
            throw new RuntimeException('Refresh token is missing for this plugin account.');
        }

        $tokens = $this->requestToken($provider, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);

        $payload = [
            'access_token_env' => (string) ($provider['access_token_env'] ?? ''),
            'refresh_token_env' => $refreshEnv,
        ];

        $this->persistTokens($account, $provider, $tokens, $payload);
        $this->persistTokenExpiry($account, $tokens);

        return $this->accounts->refreshAuthStatus($account->fresh());
    }

    /** @param  array<string, mixed>  $provider */
    protected function exchangeCode(array $provider, string $code, string $codeVerifier): array
    {
        $body = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
        ];

        if (($provider['pkce'] ?? false) === true) {
            $body['code_verifier'] = $codeVerifier;
        }

        return $this->requestToken($provider, $body);
    }

    /**
     * @param  array<string, mixed>  $provider
     * @param  array<string, string>  $body
     * @return array<string, mixed>
     */
    protected function requestToken(array $provider, array $body): array
    {
        $clientId = (string) ($provider['client_id'] ?? '');
        $clientSecret = (string) ($provider['client_secret'] ?? '');
        $tokenAuth = (string) ($provider['token_auth'] ?? 'body');

        if ($tokenAuth !== 'basic') {
            $body['client_id'] = $clientId;
        }

        if ($clientSecret !== '' && $tokenAuth !== 'basic') {
            $body['client_secret'] = $clientSecret;
        }

        $request = Http::asForm()->acceptJson();

        if ($tokenAuth === 'basic' && $clientId !== '' && $clientSecret !== '') {
            $request = $request->withHeaders([
                'Authorization' => 'Basic '.base64_encode($clientId.':'.$clientSecret),
            ]);
        }

        $response = $request->post((string) $provider['token_url'], $body);

        if (! $response->successful()) {
            throw new RuntimeException('OAuth token exchange failed: '.$response->body());
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new RuntimeException('OAuth token response was not JSON.');
        }

        return $json;
    }

    /** @param  array<string, mixed>  $tokens */
    protected function persistTokenExpiry(PluginAccount $account, array $tokens): void
    {
        $expiresIn = (int) ($tokens['expires_in'] ?? 0);

        $account->update([
            'token_expires_at' => $expiresIn > 0 ? now()->addSeconds($expiresIn) : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $provider
     */
    protected function hasRefreshToken(PluginAccount $account, array $provider): bool
    {
        $refreshEnv = (string) ($provider['refresh_token_env'] ?? '');
        $credentialMap = is_array($account->credential_map) ? $account->credential_map : [];

        if ($refreshEnv === '') {
            return false;
        }

        return $this->resolveStoredCredential($credentialMap, $refreshEnv) !== null;
    }

    /**
     * @param  array<string, string>  $credentialMap
     */
    protected function resolveStoredCredential(array $credentialMap, string $envKey): ?string
    {
        $ref = $credentialMap[$envKey] ?? null;

        if (! is_string($ref) || ! str_starts_with($ref, 'var:')) {
            return null;
        }

        $name = substr($ref, 4);
        $variable = Variable::query()->inCurrentTenant()->where('name', $name)->first();

        if ($variable === null) {
            return null;
        }

        $value = (string) ($variable->value ?? '');

        return trim($value) !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $provider
     * @param  array<string, mixed>  $tokens
     * @param  array<string, mixed>  $payload
     */
    protected function persistTokens(PluginAccount $account, array $provider, array $tokens, array $payload): void
    {
        $accessToken = (string) ($tokens['access_token'] ?? '');

        if ($accessToken === '') {
            throw new RuntimeException('OAuth response did not include an access_token.');
        }

        $accessEnv = (string) ($payload['access_token_env'] ?? $provider['access_token_env'] ?? '');

        if ($accessEnv === '') {
            throw new RuntimeException('OAuth provider is missing access_token_env.');
        }

        $credentialMap = is_array($account->credential_map) ? $account->credential_map : [];
        $accessVar = $this->resolveVariableName($credentialMap, $accessEnv, $account);
        $this->upsertCredentialVariable($accessVar, $accessToken);

        $refreshToken = (string) ($tokens['refresh_token'] ?? '');
        $refreshEnv = (string) ($payload['refresh_token_env'] ?? $provider['refresh_token_env'] ?? '');

        if ($refreshToken !== '' && $refreshEnv !== '') {
            $refreshVar = $this->resolveVariableName($credentialMap, $refreshEnv, $account, allowGenerated: true);
            $this->upsertCredentialVariable($refreshVar, $refreshToken);

            if (! isset($credentialMap[$refreshEnv])) {
                $credentialMap[$refreshEnv] = 'var:'.$refreshVar;
            }
        }

        if (! isset($credentialMap[$accessEnv]) || $credentialMap[$accessEnv] === '') {
            $credentialMap[$accessEnv] = 'var:'.$accessVar;
        }

        $this->accounts->updateCredentialMap($account, $credentialMap);
    }

    /**
     * @param  array<string, string>  $credentialMap
     */
    protected function resolveVariableName(array $credentialMap, string $envKey, PluginAccount $account, bool $allowGenerated = false): string
    {
        $ref = $credentialMap[$envKey] ?? null;

        if (is_string($ref) && str_starts_with($ref, 'var:')) {
            return substr($ref, 4);
        }

        if ($allowGenerated) {
            $install = $account->install;

            if ($install !== null) {
                $prefix = Str::upper(Str::slug($install->slug, '_'));

                return $prefix.'_'.Str::upper(Str::slug($envKey, '_'));
            }
        }

        throw new InvalidArgumentException("Credential map is missing var: binding for [{$envKey}].");
    }

    protected function upsertCredentialVariable(string $name, string $value): void
    {
        $variable = Variable::query()->inCurrentTenant()->where('name', $name)->first();

        if ($variable !== null) {
            $variable->updateTyped(Variable::TYPE_CREDENTIAL, $value, keepValueIfBlank: false);

            return;
        }

        Variable::create([
            'name' => $name,
            'type' => Variable::TYPE_CREDENTIAL,
            'value' => $value,
        ]);
    }

    /** @return array<string, mixed> */
    protected function requireProvider(string $slug): array
    {
        $provider = $this->registry->forSlug($slug);

        if ($provider === null || ! $this->registry->isConfigured($slug)) {
            throw new RuntimeException("OAuth is not configured for plugin [{$slug}].");
        }

        return $provider;
    }

    protected function redirectUri(): string
    {
        return route('neuronai-studio.plugins.oauth.callback');
    }

    protected function stateCacheKey(string $state): string
    {
        return 'neuronai_studio_plugin_oauth:'.$state;
    }

    protected function generateCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    protected function codeChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }
}
