<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\KnowledgeBases;

use DigitalElvis\NeuronAIStudio\Models\KnowledgeBase;
use DigitalElvis\NeuronAIStudio\Runtime\Rag\DocumentIngestService;
use DigitalElvis\NeuronAIStudio\Support\StudioLayout;
use Livewire\Component;

class Index extends Component
{
    public string $filter = '';

    public function delete(int $knowledgeBaseId, DocumentIngestService $ingest): void
    {
        $knowledgeBase = KnowledgeBase::findOrFail($knowledgeBaseId);
        $ingest->removeKnowledgeBase($knowledgeBase);
        session()->flash('success', 'Knowledge base deleted.');
    }

    public function render()
    {
        $query = KnowledgeBase::query()->withCount('documents')->latest();

        if ($this->filter !== '') {
            $needle = '%'.strtolower($this->filter).'%';
            $query->where(function ($builder) use ($needle) {
                $builder->whereRaw('lower(name) like ?', [$needle])
                    ->orWhereRaw('lower(slug) like ?', [$needle]);
            });
        }

        return view('neuronai-studio::livewire.knowledge-bases.index', [
            'knowledgeBases' => $query->get(),
        ])->layout('neuronai-studio::layouts.app', StudioLayout::params(
            breadcrumbs: [['label' => 'Knowledge Bases']],
            title: 'Knowledge Bases',
            headerActions: view('neuronai-studio::partials.header-actions.new-knowledge-base')->render(),
        ));
    }
}
