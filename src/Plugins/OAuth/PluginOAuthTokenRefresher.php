<?php

namespace DigitalElvis\NeuronAIStudio\Plugins\OAuth;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\AgentPluginBinding;
use DigitalElvis\NeuronAIStudio\Models\PluginAccount;
use DigitalElvis\NeuronAIStudio\Models\PluginInstall;
use DigitalElvis\NeuronAIStudio\Plugins\PluginMcpGate;

class PluginOAuthTokenRefresher
{
    public function __construct(
        protected PluginMcpGate $gate,
        protected PluginOAuthRegistry $registry,
        protected PluginOAuthService $oauth,
    ) {}

    public function prepareForMcpSlug(string $mcpSlug, ?AgentDefinition $agent = null): void
    {
        $installId = $this->gate->pluginInstallIdForMcpSlug($mcpSlug);

        if ($installId === null) {
            return;
        }

        $install = PluginInstall::query()->find($installId);

        if ($install === null || ! $this->registry->isConfigured((string) $install->slug)) {
            return;
        }

        $account = $this->resolveAccount($install, $agent);

        if ($account === null || ! $account->isConnected()) {
            return;
        }

        $this->oauth->ensureFreshAccessToken($account);
    }

    protected function resolveAccount(PluginInstall $install, ?AgentDefinition $agent): ?PluginAccount
    {
        if ($agent !== null) {
            $binding = AgentPluginBinding::query()
                ->where('agent_definition_id', $agent->id)
                ->where('plugin_install_id', $install->id)
                ->with('account')
                ->first();

            if ($binding?->account !== null) {
                return $binding->account;
            }
        }

        return $install->accounts()
            ->where('auth_status', PluginAccount::AUTH_CONNECTED)
            ->orderBy('label')
            ->first();
    }
}
