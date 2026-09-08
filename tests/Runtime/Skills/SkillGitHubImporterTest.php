<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Runtime\Skills;

use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillGitHubImporter;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;

class SkillGitHubImporterTest extends TestCase
{
    public function test_parses_tree_url(): void
    {
        $parsed = app(SkillGitHubImporter::class)->parseGitHubUrl(
            'https://github.com/anthropics/claude-plugins-official/tree/main/plugins/claude-code-setup/skills/claude-automation-recommender'
        );

        $this->assertSame('anthropics', $parsed['owner']);
        $this->assertSame('claude-plugins-official', $parsed['repo']);
        $this->assertSame('main', $parsed['ref']);
        $this->assertSame(
            'plugins/claude-code-setup/skills/claude-automation-recommender',
            $parsed['path']
        );
    }

    public function test_parses_repo_url(): void
    {
        $parsed = app(SkillGitHubImporter::class)->parseGitHubUrl(
            'https://github.com/username/repo'
        );

        $this->assertSame('username', $parsed['owner']);
        $this->assertSame('repo', $parsed['repo']);
        $this->assertSame('main', $parsed['ref']);
        $this->assertSame('', $parsed['path']);
    }
}
