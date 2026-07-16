<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\ToolDefinition;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Registry\ToolRegistry;
use DigitalElvis\NeuronAIStudio\Support\StudioLayout;
use DigitalElvis\NeuronAIStudio\Usage\UsageQuery;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $registryCount = count(app(ToolRegistry::class)->all());
        $usageTotals = app(UsageQuery::class)->aggregate(now()->subDays(30), now());

        return view('neuronai-studio::livewire.dashboard', [
            'agentCount' => AgentDefinition::count(),
            'workflowCount' => WorkflowDefinition::count(),
            'toolCount' => max(ToolDefinition::count(), $registryCount),
            'mcpServerCount' => count(app(\DigitalElvis\NeuronAIStudio\Registry\McpRegistry::class)->all()),
            'recentTraces' => StudioRun::with('thread.entity')->latest()->limit(10)->get(),
            'usageWindowLabel' => '30d',
            'usageTotals' => $usageTotals,
        ])->layout('neuronai-studio::layouts.app', StudioLayout::params(
            breadcrumbs: [['label' => 'Dashboard']],
            title: 'Dashboard',
        ));
    }
}
