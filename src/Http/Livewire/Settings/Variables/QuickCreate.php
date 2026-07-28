<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\Settings\Variables;

use DigitalElvis\NeuronAIStudio\Models\Variable;
use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Global create-variable modal (mounted in Studio layout) so Variable Input
 * pickers can open "Add" from any page.
 */
class QuickCreate extends Component
{
    public bool $showModal = false;

    public string $formType = Variable::TYPE_CREDENTIAL;

    public string $formName = '';

    public string $formValue = '';

    #[On('studio-open-create-variable')]
    public function open(): void
    {
        $this->formType = Variable::TYPE_CREDENTIAL;
        $this->formName = '';
        $this->formValue = '';
        $this->resetValidation();
        $this->showModal = true;
    }

    public function close(): void
    {
        $this->showModal = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'formType' => ['required', Rule::in([Variable::TYPE_CREDENTIAL, Variable::TYPE_GENERIC])],
            'formName' => [
                'required',
                'string',
                'max:255',
                'regex:'.Variable::NAME_PATTERN,
                Rule::unique(StudioTables::name('variables'), 'name'),
            ],
            'formValue' => ['required', 'string'],
        ], [
            'formName.regex' => 'Name must match ^[A-Z][A-Z0-9_]*$ (e.g. OPENAI_PROD).',
        ]);

        Variable::create([
            'name' => $validated['formName'],
            'type' => $validated['formType'],
            'value' => $validated['formValue'],
        ]);

        $this->dispatch('studio-variable-created', name: $validated['formName'], type: $validated['formType']);
        $this->js(
            'window.dispatchEvent(new CustomEvent("studio-variable-created", { detail: '
            .json_encode(['name' => $validated['formName'], 'type' => $validated['formType']])
            .' }))'
        );

        $this->close();
    }

    public function render()
    {
        return view('neuronai-studio::livewire.settings.variables.quick-create');
    }
}
