<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Runtime\Skills;

use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillPathPolicy;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;

class SkillPathPolicyTest extends TestCase
{
    public function test_allows_standard_prefixes(): void
    {
        $policy = new SkillPathPolicy;

        $this->assertTrue($policy->isAllowed('references/guide.md'));
        $this->assertTrue($policy->isAllowed('scripts/run.sh'));
        $this->assertTrue($policy->isAllowed('assets/cover.png'));
        $this->assertFalse($policy->isAllowed('../secret'));
        $this->assertFalse($policy->isAllowed('SKILL.md'));
    }

    public function test_executable_only_under_scripts(): void
    {
        $policy = new SkillPathPolicy;

        $this->assertTrue($policy->isExecutable('scripts/run.sh'));
        $this->assertFalse($policy->isExecutable('references/run.sh'));
        $this->assertFalse($policy->isExecutable('scripts/notes.md'));
    }
}
