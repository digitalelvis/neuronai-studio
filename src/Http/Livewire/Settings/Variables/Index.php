<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\Settings\Variables;

use DigitalElvis\NeuronAIStudio\Models\Variable;
use DigitalElvis\NeuronAIStudio\Repositories\VariableRepository;
use DigitalElvis\NeuronAIStudio\Support\StudioLayout;
use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class Index extends Component
{
    public string $filter = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $formType = Variable::TYPE_CREDENTIAL;

    public string $formName = '';

    public string $formValue = '';

    #[On('variables-open-create')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    #[On('studio-variable-created')]
    public function onVariableCreated(): void
    {
        // Re-render list when QuickCreate (layout modal) adds a variable.
    }

    public function openEdit(int $id): void
    {
        $variable = Variable::findOrFail($id);
        $this->editingId = $variable->id;
        $this->formType = $variable->type;
        $this->formName = $variable->name;
        // Credential: never prefill plaintext. Generic: may reveal.
        $this->formValue = $variable->isCredential() ? '' : (string) $variable->value;
        $this->showModal = true;
        $this->resetValidation();
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
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
                Rule::unique(StudioTables::name('variables'), 'name')->ignore($this->editingId),
            ],
            'formValue' => $this->editingId ? ['nullable', 'string'] : ['required', 'string'],
        ], [
            'formName.regex' => 'Name must match ^[A-Z][A-Z0-9_]*$ (e.g. OPENAI_PROD).',
        ]);

        if ($this->editingId) {
            $variable = Variable::findOrFail($this->editingId);
            $variable->name = $validated['formName'];
            $variable->updateTyped(
                $validated['formType'],
                $validated['formValue'] ?? '',
                keepValueIfBlank: true,
            );
            session()->flash('success', 'Variable updated.');
        } else {
            Variable::create([
                'name' => $validated['formName'],
                'type' => $validated['formType'],
                'value' => $validated['formValue'],
            ]);
            session()->flash('success', 'Variable created.');
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        Variable::findOrFail($id)->delete();
        session()->flash('success', 'Variable deleted.');
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->formType = Variable::TYPE_CREDENTIAL;
        $this->formName = '';
        $this->formValue = '';
        $this->resetValidation();
    }

    public function render(VariableRepository $repository)
    {
        $variables = $repository->allOrdered();

        if ($this->filter !== '') {
            $needle = strtolower($this->filter);
            $variables = $variables->filter(
                fn (Variable $v) => str_contains(strtolower($v->name), $needle)
                    || str_contains(strtolower($v->type), $needle)
            )->values();
        }

        return view('neuronai-studio::livewire.settings.variables.index', [
            'variables' => $variables,
        ])->layout('neuronai-studio::layouts.app', StudioLayout::params(
            breadcrumbs: [
                ['label' => 'Settings'],
                ['label' => 'Global Variables'],
            ],
            title: 'Global Variables',
            headerActions: view('neuronai-studio::partials.header-actions.new-variable')->render(),
        ));
    }
}
