<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Runtime\Skills;

use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillParser;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use InvalidArgumentException;

class SkillParserTest extends TestCase
{
    public function test_parses_valid_skill_markdown(): void
    {
        $markdown = <<<'MD'
---
name: prompt-engineer
description: Optimize agent prompts for clarity and safety.
license: MIT
metadata:
  author: studio
---

# Prompt engineering

Follow these rules when revising prompts.
MD;

        $parsed = app(SkillParser::class)->parse($markdown);

        $this->assertSame('prompt-engineer', $parsed['slug']);
        $this->assertSame('Optimize agent prompts for clarity and safety.', $parsed['description']);
        $this->assertSame('MIT', $parsed['license']);
        $this->assertSame(['author' => 'studio'], $parsed['metadata']);
        $this->assertStringContainsString('# Prompt engineering', $parsed['body']);
    }

    public function test_rejects_missing_frontmatter(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(SkillParser::class)->parse('# No frontmatter');
    }

    public function test_rejects_invalid_slug(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(SkillParser::class)->validateFields('Invalid_Slug', 'Valid description.');
    }

    public function test_builds_frontmatter_from_fields(): void
    {
        $frontmatter = app(SkillParser::class)->buildFrontmatter([
            'name' => 'my-skill',
            'description' => 'Does things.',
        ]);

        $this->assertStringContainsString('name: "my-skill"', $frontmatter);
        $this->assertStringContainsString('description: "Does things."', $frontmatter);
        $this->assertStringStartsWith('---', $frontmatter);
        $this->assertStringEndsWith('---', trim($frontmatter));
    }
}
