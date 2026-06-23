<?php

namespace ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Tools;

use ElvisLopesDigital\NeuronAIStudio\Models\ToolDefinition;
use ElvisLopesDigital\NeuronAIStudio\Registry\ToolRegistry;
use Livewire\Component;

class Index extends Component
{
    public string $filter = '';

    public function delete(int $id): void
    {
        ToolDefinition::findOrFail($id)->delete();
        session()->flash('success', 'Tool deleted.');
    }

    public function render()
    {
        $registry = app(ToolRegistry::class);
        $catalog = collect($registry->all());

        if ($this->filter !== '') {
            $needle = strtolower($this->filter);

            $catalog = $catalog->filter(function (array $entry) use ($needle) {
                return str_contains(strtolower($entry['label']), $needle)
                    || str_contains(strtolower($entry['ref']), $needle)
                    || str_contains(strtolower($entry['category']), $needle)
                    || str_contains(strtolower($entry['type']), $needle);
            });
        }

        return view('neuronai-studio::livewire.tools.index', [
            'tools' => $catalog->sortBy('label')->values(),
            'categoryLabels' => [
                'builtin' => 'Built-in',
                'app' => 'App Class',
                'studio' => 'Studio',
                'mcp' => 'MCP',
            ],
        ])->layout('neuronai-studio::layouts.app', ['title' => 'Tools']);
    }
}
