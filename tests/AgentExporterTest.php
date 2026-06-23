<?php

namespace ElvisLopesDigital\NeuronAIStudio\Tests;

use ElvisLopesDigital\NeuronAIStudio\Codegen\AgentExporter;
use ElvisLopesDigital\NeuronAIStudio\Models\AgentDefinition;
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
        $this->assertStringContainsString('class SupportBotAgent extends Agent', file_get_contents($files[0]));

        File::deleteDirectory($exportPath);
    }
}
