<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Codegen\AgentExporter;
use DigitalElvis\NeuronAIStudio\Codegen\ExportedSkillLoader;
use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\SkillDefinition;
use Illuminate\Support\Facades\File;

class AgentExporterSkillsTest extends TestCase
{
    public function test_exports_skill_snapshot_and_wires_runtime_tools(): void
    {
        $exportPath = storage_path('framework/testing/neuron-skills');
        config(['neuronai-studio.export_path' => $exportPath]);
        config(['neuronai-studio.export_namespace' => 'App\\Neuron']);

        File::deleteDirectory($exportPath);

        $skill = SkillDefinition::create([
            'slug' => 'support-playbook',
            'description' => 'Handle support tickets.',
            'body' => 'Always greet the user.',
            'resources' => [
                'references/tone.md' => 'Use a friendly tone.',
            ],
        ]);

        $agent = AgentDefinition::create([
            'name' => 'Support Bot',
            'slug' => 'support-bot',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are support.',
            'skills' => [
                ['ref' => $skill->bindingRef()],
            ],
        ]);

        $files = app(AgentExporter::class)->export($agent);

        $this->assertGreaterThan(1, count($files));
        $this->assertFileExists($exportPath.'/skills/support-playbook/SKILL.md');
        $this->assertFileExists($exportPath.'/skills/support-playbook/references/tone.md');

        $agentClass = file_get_contents($exportPath.'/SupportBotAgent.php');
        $this->assertStringContainsString('ActivateSkillTool', $agentClass);
        $this->assertStringContainsString('SkillCatalogInjector', $agentClass);
        $this->assertStringContainsString("'support-playbook'", $agentClass);

        $catalog = ExportedSkillLoader::fromDirectory($exportPath.'/skills', ['support-playbook']);
        $this->assertFalse($catalog->isEmpty());
        $this->assertSame('Handle support tickets.', $catalog->get('support-playbook')->description);

        File::deleteDirectory($exportPath);
    }
}
