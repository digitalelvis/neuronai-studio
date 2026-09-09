<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Skills;

interface SkillContent
{
    public function slug(): string;

    public function description(): string;

    public function body(): string;

    /** @return array<string, string> */
    public function files(): array;

    public function skillMarkdown(): string;

    public function isPathReadable(string $path): bool;
}
