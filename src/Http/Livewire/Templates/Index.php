<?php

namespace ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Templates;

use ElvisLopesDigital\NeuronAIStudio\Registry\TemplateRegistry;
use ElvisLopesDigital\NeuronAIStudio\Services\TemplateInstaller;
use ElvisLopesDigital\NeuronAIStudio\Support\StudioLayout;
use Livewire\Component;
use Throwable;

class Index extends Component
{
    public string $typeFilter = 'all';

    public string $complexityFilter = 'all';

    public string $categoryFilter = 'all';

    public function mount(): void
    {
        $category = request()->query('category');

        if (is_string($category) && $category !== '') {
            $this->categoryFilter = $category;
            $this->typeFilter = 'agent';
        }
    }

    public function updatedTypeFilter(): void
    {
        if ($this->typeFilter !== 'workflow') {
            $this->complexityFilter = 'all';
        }
    }

    public function useTemplate(string $type, string $id): void
    {
        if (! config('neuronai-studio.templates_enabled', true)) {
            session()->flash('error', 'Templates are disabled.');

            return;
        }

        try {
            $installer = app(TemplateInstaller::class);

            if ($type === 'agent') {
                $agent = $installer->installAgent($id);
                session()->flash('success', 'Agent created from template.');
                $this->redirect(route('neuronai-studio.agents.edit', $agent));

                return;
            }

            if ($type === 'workflow') {
                $workflow = $installer->installWorkflow($id);
                session()->flash('success', 'Workflow created from template.');
                $this->redirect(route('neuronai-studio.workflows.edit', $workflow));

                return;
            }

            session()->flash('error', 'Unknown template type.');
        } catch (Throwable $exception) {
            session()->flash('error', $exception->getMessage());
        }
    }

    public function render()
    {
        $registry = app(TemplateRegistry::class);
        $complexity = $this->typeFilter === 'workflow' && $this->complexityFilter !== 'all'
            ? $this->complexityFilter
            : null;

        $type = $this->typeFilter === 'all' ? null : $this->typeFilter;

        $templates = $registry->all($type, $complexity);

        if ($this->categoryFilter !== 'all') {
            $templates = array_values(array_filter(
                $templates,
                fn (array $template) => ($template['category'] ?? '') === $this->categoryFilter,
            ));
        }

        return view('neuronai-studio::livewire.templates.index', [
            'templates' => $templates,
        ])->layout('neuronai-studio::layouts.app', StudioLayout::params(
            breadcrumbs: [['label' => 'Templates']],
            title: 'Templates',
        ));
    }
}
