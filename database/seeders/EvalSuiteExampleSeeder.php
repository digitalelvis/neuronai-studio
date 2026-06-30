<?php

namespace Database\Seeders;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\EvalSuite;
use Illuminate\Database\Seeder;

class EvalSuiteExampleSeeder extends Seeder
{
    public function run(): void
    {
        $agent = AgentDefinition::query()->where('slug', 'support-assistant')->first();

        if ($agent === null) {
            return;
        }

        EvalSuite::query()->updateOrCreate(
            [
                'agent_definition_id' => $agent->id,
                'slug' => 'support-basic',
            ],
            [
                'name' => 'Support Basic',
                'dataset' => [
                    [
                        'input' => 'What are your support hours?',
                        'reference' => '9',
                        '_assertions' => [
                            ['type' => 'contains_any', 'values' => ['monday', 'hours', '9']],
                        ],
                    ],
                    [
                        'input' => 'I need to speak with a human',
                        'reference' => 'human',
                    ],
                ],
                'metadata' => [
                    'template' => 'support-assistant',
                ],
            ],
        );
    }
}
