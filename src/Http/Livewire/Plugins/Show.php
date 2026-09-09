<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\Plugins;

use DigitalElvis\NeuronAIStudio\Models\PluginInstall;
use DigitalElvis\NeuronAIStudio\Plugins\PluginPolicy;
use Livewire\Component;

class Show extends Component
{
    public function mount(mixed $install = null): void
    {
        if (! app(PluginPolicy::class)->enabled()) {
            abort(404);
        }

        $id = $install instanceof PluginInstall
            ? $install->id
            : (is_numeric($install) ? (int) $install : null);

        if ($id === null) {
            abort(404);
        }

        $this->redirectRoute('neuronai-studio.plugins.index', [
            'connector' => 'plugin_install:'.$id,
        ]);
    }

    public function render()
    {
        return <<<'HTML'
<div></div>
HTML;
    }
}
