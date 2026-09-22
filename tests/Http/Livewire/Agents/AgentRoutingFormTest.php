<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Http\Livewire\Agents;

use DigitalElvis\NeuronAIStudio\Http\Livewire\Agents\Edit;
use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use Livewire\Livewire;

class AgentRoutingFormTest extends TestCase
{
    public function test_saves_routing_config(): void
    {
        $agent = AgentDefinition::create([
            'name' => 'Routed Agent',
            'slug' => 'routed-'.uniqid(),
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
                'routing' => [
                    'enabled' => true,
                    'classifier_api_key' => 'var:TYPESAFE',
                    'easy_max' => 0.33,
                    'medium_max' => 0.70,
                    'tiers' => [
                        'easy' => ['provider' => 'openai', 'model' => 'gpt-4o-mini', 'api_key' => null],
                        'hard' => ['provider' => 'openai', 'model' => 'o3', 'api_key' => null],
                    ],
                ],
            ])
            ->assertHasNoErrors();

        $agent->refresh();
        $this->assertTrue($agent->routing_config['enabled']);
        $this->assertSame('var:TYPESAFE', $agent->routing_config['classifier_api_key']);
        $this->assertSame('gpt-4o-mini', $agent->routing_config['tiers']['easy']['model']);
        $this->assertSame('o3', $agent->routing_config['tiers']['hard']['model']);
    }

    public function test_omitted_routing_keeps_the_stored_envelope(): void
    {
        $stored = [
            'enabled' => true,
            'classifier_api_key' => null,
            'easy_max' => 0.33,
            'medium_max' => 0.7,
            'tiers' => [
                'hard' => ['provider' => 'openai', 'model' => 'o3', 'api_key' => null],
            ],
        ];

        $agent = AgentDefinition::create([
            'name' => 'Keep Routing',
            'slug' => 'keep-routing-'.uniqid(),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Test',
            'tools' => [],
            'routing_config' => $stored,
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
            ])
            ->assertHasNoErrors();

        $agent->refresh();
        $this->assertSame($stored, $agent->routing_config);
    }

    public function test_invalid_routing_does_not_persist(): void
    {
        $agent = AgentDefinition::create([
            'name' => 'Bad Routing',
            'slug' => 'bad-routing-'.uniqid(),
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
                'routing' => [
                    'enabled' => true,
                    'tiers' => [],
                ],
            ])
            ->assertHasErrors(['routing']);

        $agent->refresh();
        $this->assertNull($agent->routing_config);
    }
}
