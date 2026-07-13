<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Http\Livewire\StreamAdapters\Index;
use Livewire\Livewire;

class StreamAdaptersCatalogTest extends TestCase
{
    public function test_catalog_renders_successfully(): void
    {
        Livewire::test(Index::class)
            ->assertStatus(200)
            ->assertViewHas('available')
            ->assertViewHas('roadmap')
            ->assertSee('Vercel AI SDK')
            ->assertSee('AG-UI');
    }
}
