<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Runtime\Skills;

use DigitalElvis\NeuronAIStudio\Models\SkillDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillCatalog;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillCatalogEntry;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillScriptSandbox;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;

class SkillScriptSandboxTest extends TestCase
{
    public function test_runs_bash_script_when_enabled(): void
    {
        config(['neuronai-studio.skills.execution.enabled' => true]);

        $definition = new SkillDefinition([
            'slug' => 'echo-skill',
            'description' => 'Echo skill',
            'body' => 'Run the script.',
            'resources' => [
                'scripts/hello.sh' => "#!/bin/bash\necho hello-from-skill\n",
            ],
        ]);

        $entry = new SkillCatalogEntry('echo-skill', 'Echo skill', $definition);
        $result = app(SkillScriptSandbox::class)->run($entry, 'scripts/hello.sh');

        $this->assertSame(0, $result['exit_code']);
        $this->assertStringContainsString('hello-from-skill', $result['stdout']);
    }

    public function test_rejects_when_disabled(): void
    {
        config(['neuronai-studio.skills.execution.enabled' => false]);

        $definition = new SkillDefinition([
            'slug' => 'echo-skill',
            'description' => 'Echo skill',
            'body' => 'Run',
            'resources' => ['scripts/hello.sh' => "#!/bin/bash\necho hi\n"],
        ]);

        $entry = new SkillCatalogEntry('echo-skill', 'Echo skill', $definition);
        $result = app(SkillScriptSandbox::class)->run($entry, 'scripts/hello.sh');

        $this->assertNotSame(0, $result['exit_code']);
        $this->assertStringContainsString('disabled', $result['stderr']);
    }
}
