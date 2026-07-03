<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Rag;

use DigitalElvis\NeuronAIStudio\Http\Livewire\Workflows\Editor;
use DigitalElvis\NeuronAIStudio\Models\KnowledgeBase;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use Livewire\Livewire;

class WorkflowEditorRagConfigTest extends TestCase
{
    public function test_editor_exposes_knowledge_bases_to_canvas(): void
    {
        $kb = KnowledgeBase::create([
            'name' => 'Onboarding KB',
            'embeddings_provider' => 'fake',
            'vector_store_driver' => 'memory',
        ]);

        Livewire::test(Editor::class)
            ->assertViewHas('knowledgeBasesForCanvas', function (array $bases) use ($kb) {
                return count($bases) === 1
                    && $bases[0]['id'] === $kb->getKey()
                    && $bases[0]['name'] === 'Onboarding KB';
            });
    }
}
