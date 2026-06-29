<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\Agents\Evals;

use DigitalElvis\NeuronAIStudio\Evaluation\EloquentEvaluationOutput;
use DigitalElvis\NeuronAIStudio\Evaluation\SuiteEvaluator;
use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\EvalRun;
use DigitalElvis\NeuronAIStudio\Models\EvalSuite;
use DigitalElvis\NeuronAIStudio\Support\StudioLayout;
use Illuminate\Support\Str;
use Livewire\Component;
use NeuronAI\Evaluation\Runner\EvaluatorRunner;
use Throwable;

class Edit extends Component
{
    public AgentDefinition $agent;

    public ?EvalSuite $suite = null;

    public string $name = '';

    public string $datasetJson = '';

    public ?int $judgeAgentId = null;

    public bool $useFakeProvider = false;

    public function mount(AgentDefinition $agent, ?EvalSuite $suite = null): void
    {
        $this->agent = $agent;
        $this->suite = $suite;

        if ($suite?->exists) {
            abort_unless($suite->agent_definition_id === $agent->id, 404);

            $this->name = $suite->name;
            $this->judgeAgentId = $suite->judge_agent_definition_id;
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
            'judgeAgentId' => 'nullable|integer|exists:agent_definitions,id',
        ]);

        $dataset = json_decode($this->datasetJson, true);

        if (! is_array($dataset)) {
            $this->addError('datasetJson', 'Dataset must be valid JSON array.');

            return;
        }

        if (SuiteEvaluator::datasetRequiresJudge($dataset) && $this->judgeAgentId === null) {
            $this->addError('judgeAgentId', 'A judge agent is required when the dataset uses AI judge assertions.');

            return;
        }

        $judgeAgentId = $this->judgeAgentId ?: null;

        $payload = [
            'agent_definition_id' => $this->agent->id,
            'judge_agent_definition_id' => $judgeAgentId,
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

        $dataset = $this->suite->dataset ?? [];

        if (SuiteEvaluator::datasetRequiresJudge($dataset) && $this->suite->judge_agent_definition_id === null) {
            session()->flash('error', 'Configure a judge agent before running AI judge assertions.');

            return;
        }

        $this->suite->loadMissing('judgeAgent');

        $run = EvalRun::create([
            'eval_suite_id' => $this->suite->id,
            'agent_definition_id' => $this->agent->id,
            'status' => 'running',
            'provider' => $this->agent->provider,
            'model' => $this->agent->model,
            'judge_agent_definition_id' => $this->suite->judge_agent_definition_id,
            'judge_provider' => $this->suite->judgeAgent?->provider,
            'judge_model' => $this->suite->judgeAgent?->model,
            'started_at' => now(),
        ]);

        try {
            $summary = (new EvaluatorRunner)->run(new SuiteEvaluator(
                $this->suite->fresh(['judgeAgent.mcpBindings', 'agentDefinition.mcpBindings']),
                fakeAgentProvider: $this->useFakeProvider,
            ));
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

    public function render()
    {
        return view('neuronai-studio::livewire.agents.evals.edit', [
            'agents' => AgentDefinition::query()->orderBy('name')->get(['id', 'name', 'slug']),
        ])->layout('neuronai-studio::layouts.app', StudioLayout::params(
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
