<?php

namespace DigitalElvis\NeuronAIStudio\Plugins;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\AgentPluginBinding;
use DigitalElvis\NeuronAIStudio\Models\McpServer;
use DigitalElvis\NeuronAIStudio\Models\PluginAccount;
use DigitalElvis\NeuronAIStudio\Models\PluginInstall;
use DigitalElvis\NeuronAIStudio\Registry\McpRegistry;

class PluginMcpGate
{
    public function shouldSkipBinding(AgentDefinition $agent, string $mcpSlug): bool
    {
        $entry = app(McpRegistry::class)->find($mcpSlug);

        if ($entry === null) {
            return false;
        }

        $metadata = is_array($entry['metadata'] ?? null) ? $entry['metadata'] : [];
        $installId = $metadata['plugin_install_id'] ?? null;

        if ($installId === null) {
            return false;
        }

        $binding = AgentPluginBinding::query()
            ->where('agent_definition_id', $agent->id)
            ->where('plugin_install_id', $installId)
            ->with('account')
            ->first();

        if ($binding === null) {
            return true;
        }

        $account = $binding->account;

        if ($account === null) {
            return true;
        }

        return ! $account->isConnected();
    }

    public function pluginInstallIdForMcpSlug(string $mcpSlug): ?int
    {
        $entry = app(McpRegistry::class)->find($mcpSlug);
        $metadata = is_array($entry['metadata'] ?? null) ? $entry['metadata'] : [];
        $installId = $metadata['plugin_install_id'] ?? null;

        return is_numeric($installId) ? (int) $installId : null;
    }
}
