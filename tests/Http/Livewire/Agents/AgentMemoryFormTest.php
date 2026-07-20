<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Http\Livewire\Agents;

use DigitalElvis\NeuronAIStudio\Http\Livewire\Agents\Edit;
use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use Livewire\Livewire;

class AgentMemoryFormTest extends TestCase
{
    public function test_round_trips_memory_config_on_save(): void
    {
        $agent = AgentDefinition::create([
            'name' => 'Memory Form Agent',
            'slug' => 'memory-form-'.uniqid(),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Test',
            'tools' => [],
        ]);

        Livewire::test(Edit::class, ['agent' => $agent])
            ->call('saveFromReact', [
                'name' => $agent->name,
                'description' => '',
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'instructions' => 'Test',
                'selectedToolRefs' => [],
                'toolAdvanced' => [],
                'selectedMcpSlugs' => [],
                'mcpAdvanced' => [],
                'tool_max_runs' => null,
                'parallel_tool_calls' => null,
                'memory_context_window' => 4000,
                'memory_driver' => 'in_memory',
                'memory_summarization_enabled' => true,
                'memory_summarization_threshold' => 0.75,
            ])
            ->assertHasNoErrors();

        $agent->refresh();
        $this->assertSame([
            'context_window' => 4000,
            'driver' => 'in_memory',
            'summarization_enabled' => true,
            'summarization_threshold' => 0.75,
        ], $agent->memory_config);
    }

    public function test_untouched_memory_fields_keep_null_envelope(): void
    {
        $agent = AgentDefinition::create([
            'name' => 'Null Memory Agent',
            'slug' => 'null-memory-'.uniqid(),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Test',
            'tools' => [],
            'memory_config' => null,
        ]);

        Livewire::test(Edit::class, ['agent' => $agent])
            ->call('saveFromReact', [
                'name' => $agent->name,
                'description' => '',
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'instructions' => 'Test',
                'selectedToolRefs' => [],
                'toolAdvanced' => [],
                'selectedMcpSlugs' => [],
                'mcpAdvanced' => [],
                'tool_max_runs' => null,
                'parallel_tool_calls' => false,
            ])
            ->assertHasNoErrors();

        $agent->refresh();
        $this->assertNull($agent->memory_config);
    }

    public function test_rejects_invalid_context_window(): void
    {
        $agent = AgentDefinition::create([
            'name' => 'Invalid Memory Agent',
            'slug' => 'invalid-memory-'.uniqid(),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Test',
            'tools' => [],
        ]);

        Livewire::test(Edit::class, ['agent' => $agent])
            ->call('saveFromReact', [
                'name' => $agent->name,
                'description' => '',
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'instructions' => 'Test',
                'selectedToolRefs' => [],
                'toolAdvanced' => [],
                'selectedMcpSlugs' => [],
                'mcpAdvanced' => [],
                'memory_context_window' => 0,
            ])
            ->assertHasErrors(['memory_context_window']);
    }
}
