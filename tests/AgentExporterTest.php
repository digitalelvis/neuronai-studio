<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Codegen\AgentExporter;
use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use Illuminate\Support\Facades\File;

class AgentExporterTest extends TestCase
{
    public function test_exports_agent_class(): void
    {
        $exportPath = storage_path('framework/testing/neuron');
        config(['neuronai-studio.export_path' => $exportPath]);
        config(['neuronai-studio.export_namespace' => 'App\\Neuron']);

        File::deleteDirectory($exportPath);

        $agent = AgentDefinition::create([
            'name' => 'Support Bot',
            'slug' => 'support-bot',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are support.',
        ]);

        $files = app(AgentExporter::class)->export($agent);

        $this->assertCount(1, $files);
        $this->assertFileExists($files[0]);
        $content = file_get_contents($files[0]);
        $this->assertStringContainsString('class SupportBotAgent extends Agent', $content);
        $this->assertStringContainsString('use NeuronAI\\Providers\\OpenAI\\OpenAI;', $content);
        $this->assertStringContainsString("new OpenAI((string) config('neuron.provider.openai.key'), 'gpt-4o-mini')", $content);
        $this->assertStringNotContainsString('NeuronAI\\Laravel', $content);

        File::deleteDirectory($exportPath);
    }

    public function test_exports_router_provider_when_routing_is_enabled(): void
    {
        $exportPath = storage_path('framework/testing/neuron-routing');
        config(['neuronai-studio.export_path' => $exportPath]);
        config(['neuronai-studio.export_namespace' => 'App\\Neuron']);

        File::deleteDirectory($exportPath);

        $agent = AgentDefinition::create([
            'name' => 'Routed Bot',
            'slug' => 'routed-bot',
            'provider' => 'openai',
            'model' => 'o3',
            'instructions' => 'You are support.',
            'routing_config' => [
                'enabled' => true,
                'classifier_api_key' => 'var:TYPESAFE',
                'easy_max' => 0.33,
                'medium_max' => 0.70,
                'tiers' => [
                    'easy' => ['provider' => 'openai', 'model' => 'gpt-4o-mini', 'api_key' => null],
                    'hard' => ['provider' => 'openai', 'model' => 'o3', 'api_key' => null],
                ],
            ],
        ]);

        $files = app(AgentExporter::class)->export($agent);
        $content = file_get_contents($files[0]);

        $this->assertStringContainsString('use NeuronAI\\Router\\RouterProvider;', $content);
        $this->assertStringContainsString('use NeuronAI\\Router\\Rules\\DifficultyRule;', $content);
        $this->assertStringContainsString('use DigitalElvis\\NeuronAIStudio\\Registry\\ClassifierRegistry;', $content);
        $this->assertStringContainsString('app(ClassifierRegistry::class)->resolve(', $content);
        $this->assertStringNotContainsString('api.typesafe.ai', $content);
        $this->assertStringNotContainsString('new TypeSafeAI', $content);
        $this->assertStringContainsString('RouterProvider::make()', $content);
        $this->assertStringContainsString("->addProvider('easy'", $content);
        $this->assertStringContainsString("->addProvider('hard'", $content);
        $this->assertStringContainsString("->setDefaultProvider('hard')", $content);
        $this->assertStringContainsString("->easy('easy'", $content);
        $this->assertStringContainsString("->hard('hard')", $content);
        $this->assertStringContainsString("'var:TYPESAFE'", $content);
        $this->assertStringNotContainsString('sk-', $content);

        File::deleteDirectory($exportPath);
    }
}
