<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Skills\Tools;

use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillCatalog;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillFileReader;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

/** Alias tool name preferred by agentskills-style clients. */
class ReadSkillFileTool extends Tool
{
    public function __construct(
        protected SkillCatalog $catalog,
        protected ?SkillFileReader $reader = null,
    ) {
        parent::__construct(
            name: 'read_skill_file',
            description: 'Read a file from an attached skill (references/, scripts/, or assets/). Prefer this after activate_skill.',
            properties: [
                new ToolProperty(
                    name: 'name',
                    type: PropertyType::STRING,
                    description: 'The skill name (slug)',
                    required: true,
                ),
                new ToolProperty(
                    name: 'path',
                    type: PropertyType::STRING,
                    description: 'Relative path (e.g. references/guide.md)',
                    required: true,
                ),
            ],
        );

        $this->reader ??= app(SkillFileReader::class);
        $this->setCallable(fn (string $name, string $path) => ($this->reader ?? app(SkillFileReader::class))->read($this->catalog, $name, $path));
    }

    public function __invoke(string $name, string $path): string
    {
        return ($this->reader ?? app(SkillFileReader::class))->read($this->catalog, $name, $path);
    }
}
