<?php

namespace DigitalElvis\NeuronAIStudio\Plugins;

use DigitalElvis\NeuronAIStudio\Models\PluginAccount;
use DigitalElvis\NeuronAIStudio\Models\PluginInstall;
use DigitalElvis\NeuronAIStudio\Models\Variable;
use DigitalElvis\NeuronAIStudio\Repositories\VariableRepository;
use Illuminate\Support\Str;

class PluginAccountService
{
    public function __construct(
        protected VariableRepository $variables,
    ) {}

    /**
     * @param  array<int, string>  $requiredEnvKeys
     * @param  array<string, array<string, mixed>>  $mcpServers
     */
    public function createDefaultAccount(PluginInstall $install, array $requiredEnvKeys, array $mcpServers): PluginAccount
    {
        $credentialMap = $this->buildDefaultCredentialMap($install, $requiredEnvKeys, $mcpServers);

        $account = PluginAccount::create([
            'plugin_install_id' => $install->id,
            'label' => 'default',
            'auth_status' => PluginAccount::AUTH_NEEDS,
            'credential_map' => $credentialMap,
        ]);

        return $this->refreshAuthStatus($account);
    }

    /**
     * @param  array<string, string>  $credentialMap
     */
    public function updateCredentialMap(PluginAccount $account, array $credentialMap): PluginAccount
    {
        $account->update(['credential_map' => $credentialMap]);

        return $this->refreshAuthStatus($account->fresh());
    }

    public function disconnect(PluginAccount $account): PluginAccount
    {
        $map = is_array($account->credential_map) ? $account->credential_map : [];

        foreach ($map as $ref) {
            if (! is_string($ref) || ! str_starts_with($ref, 'var:')) {
                continue;
            }

            $name = substr($ref, 4);
            $variable = Variable::query()->inCurrentTenant()->where('name', $name)->first();

            if ($variable !== null) {
                $variable->updateTyped(Variable::TYPE_CREDENTIAL, '', keepValueIfBlank: false);
            }
        }

        $account->update(['token_expires_at' => null]);

        return $this->refreshAuthStatus($account->fresh());
    }

    public function refreshAuthStatus(PluginAccount $account): PluginAccount
    {
        $map = is_array($account->credential_map) ? $account->credential_map : [];

        if ($map === []) {
            $account->update(['auth_status' => PluginAccount::AUTH_CONNECTED]);

            return $account->fresh();
        }

        foreach ($map as $envKey => $ref) {
            if (! is_string($ref) || $ref === '') {
                $account->update(['auth_status' => PluginAccount::AUTH_NEEDS]);

                return $account->fresh();
            }

            if (! $this->refResolves($ref)) {
                $account->update(['auth_status' => PluginAccount::AUTH_NEEDS]);

                return $account->fresh();
            }
        }

        $account->update(['auth_status' => PluginAccount::AUTH_CONNECTED]);

        return $account->fresh();
    }

    protected function refResolves(string $ref): bool
    {
        if (str_starts_with($ref, 'var:')) {
            $name = substr($ref, 4);

            try {
                $value = $this->variables->resolveValue($name);

                return is_string($value) && trim($value) !== '';
            } catch (\Throwable) {
                return false;
            }
        }

        if (str_starts_with($ref, 'env:')) {
            $name = substr($ref, 4);
            $value = env($name);

            return is_string($value) && trim($value) !== '';
        }

        $value = env($ref);

        return is_string($value) && trim($value) !== '';
    }

    /**
     * @param  array<int, string>  $requiredEnvKeys
     * @param  array<string, array<string, mixed>>  $mcpServers
     * @return array<string, string>
     */
    protected function buildDefaultCredentialMap(PluginInstall $install, array $requiredEnvKeys, array $mcpServers): array
    {
        $map = [];
        $prefix = Str::upper(Str::slug($install->slug, '_'));

        foreach ($requiredEnvKeys as $envKey) {
            if (! is_string($envKey) || $envKey === '') {
                continue;
            }

            $map[$envKey] = 'var:'.$prefix.'_'.Str::upper(Str::slug($envKey, '_'));
        }

        return $map;
    }
}
