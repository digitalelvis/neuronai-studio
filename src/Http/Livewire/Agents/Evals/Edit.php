<?php

namespace ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Agents\Evals;

use ElvisLopesDigital\NeuronAIStudio\Evaluation\EloquentEvaluationOutput;
use ElvisLopesDigital\NeuronAIStudio\Evaluation\SuiteEvaluator;
use ElvisLopesDigital\NeuronAIStudio\Models\AgentDefinition;
use ElvisLopesDigital\NeuronAIStudio\Models\EvalRun;
use ElvisLopesDigital\NeuronAIStudio\Models\EvalSuite;
use ElvisLopesDigital\NeuronAIStudio\Registry\ProviderRegistry;
use ElvisLopesDigital\NeuronAIStudio\Support\StudioLayout;
use Illuminate\Support\Str;
use Livewire\Component;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Evaluation\Runner\EvaluatorRunner;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Testing\FakeAIProvider;
use Throwable;

class Edit extends Component
{
    public AgentDefinition $agent;

    public ?EvalSuite $suite = null;

    public string $name = '';

    public string $datasetJson = '';

    public bool $useFakeProvider = false;

    public function mount(AgentDefinition $agent, ?EvalSuite $suite = null): void
    {
        $this->agent = $agent;
        $this->suite = $suite;

        if ($suite?->exists) {
            abort_unless($suite->agent_definition_id === $agent->id, 404);

            $this->name = $suite->name;
            $this->datasetJson = json_encode($suite->dataset ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            $this->datasetJson = json_encode([
                [
                    'input' => 'What are your support hours?',
                    'reference' => '9',
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'datasetJson' => 'required|string',
        ]);

        $dataset = json_decode($this->datasetJson, true);

        if (! is_array($dataset)) {
            $this->addError('datasetJson', 'Dataset must be valid JSON array.');

            return;
        }

        $payload = [
            'agent_definition_id' => $this->agent->id,
            'name' => $this->name,
            'slug' => Str::slug($this->name),
            'dataset' => $dataset,
        ];

        if ($this->suite?->exists) {
            $this->suite->update($payload);
            session()->flash('success', 'Eval suite updated.');
        } else {
            $this->suite = EvalSuite::create($payload);
            session()->flash('success', 'Eval suite created.');
        }

        $this->redirectRoute('neuronai-studio.agents.evals.edit', [
            'agent' => $this->agent,
            'suite' => $this->suite,
        ]);
    }

    public function runSuite(): void
    {
        if (! $this->suite?->exists) {
            $this->save();
        }

        if (! $this->suite?->exists) {
            return;
        }

        if ($this->useFakeProvider) {
            $this->bindFakeProvider();
        }

        $run = EvalRun::create([
            'eval_suite_id' => $this->suite->id,
            'agent_definition_id' => $this->agent->id,
            'status' => 'running',
            'provider' => $this->agent->provider,
            'model' => $this->agent->model,
            'started_at' => now(),
        ]);

        try {
            $summary = (new EvaluatorRunner)->run(new SuiteEvaluator($this->suite->fresh()));
            (new EloquentEvaluationOutput($run))->output($summary);

            session()->flash('success', "Eval run completed: {$summary->getPassedCount()}/{$summary->getTotalCount()} passed.");
        } catch (Throwable $e) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
            ]);

            session()->flash('error', $e->getMessage());

            return;
        }

        $this->redirectRoute('neuronai-studio.agents.eval-runs.show', $run);
    }

    protected function bindFakeProvider(): void
    {
        app()->singleton(ProviderRegistry::class, function () {
            return new class extends ProviderRegistry
            {
                public function resolve(string $provider, ?string $model = null, array $parameters = []): AIProviderInterface
                {
                    return new FakeAIProvider(new AssistantMessage('Eval fake response'));
                }
            };
        });
    }

    public function render()
    {
        return view('neuronai-studio::livewire.agents.evals.edit')->layout('neuronai-studio::layouts.app', StudioLayout::params(
            breadcrumbs: [
                ['label' => 'Agents', 'url' => route('neuronai-studio.agents.index')],
                ['label' => $this->agent->name, 'url' => route('neuronai-studio.agents.edit', $this->agent)],
                ['label' => 'Evals', 'url' => route('neuronai-studio.agents.evals.index', $this->agent)],
                ['label' => $this->suite?->exists ? $this->name : 'New Suite'],
            ],
            title: ($this->suite?->exists ? 'Edit' : 'Create').' Eval Suite',
        ));
    }
}
