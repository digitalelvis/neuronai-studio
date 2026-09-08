<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Skills\Tools;

use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillCatalog;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillFileReader;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

class ReadSkillResourceTool extends Tool
{
    public function __construct(
        protected SkillCatalog $catalog,
        protected ?SkillFileReader $reader = null,
    ) {
        parent::__construct(
            name: 'read_skill_resource',
            description: 'Read a file from an attached skill (paths under references/, scripts/, or assets/). Use after activate_skill when you need detailed material.',
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
                    description: 'Relative path (e.g. references/guide.md or scripts/run.sh)',
                    required: true,
                ),
            ],
        );

        $this->reader ??= app(SkillFileReader::class);
        $this->setCallable(fn (string $name, string $path) => $this->read($name, $path));
    }

    public function __invoke(string $name, string $path): string
    {
        return $this->read($name, $path);
    }

    protected function read(string $name, string $path): string
    {
        return $this->reader->read($this->catalog, $name, $path);
    }
}
