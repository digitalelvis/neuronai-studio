<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Skills\Tools;

use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillCatalog;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillScriptSandbox;
use NeuronAI\Tools\ArrayProperty;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

class RunSkillScriptTool extends Tool
{
    public function __construct(
        protected SkillCatalog $catalog,
        protected ?SkillScriptSandbox $sandbox = null,
    ) {
        parent::__construct(
            name: 'run_skill_script',
            description: 'Execute a script from an attached skill under scripts/. Pass argv as separate string arguments. Requires skills.execution.enabled.',
            properties: [
                new ToolProperty(
                    name: 'name',
                    type: PropertyType::STRING,
                    description: 'The skill name (slug)',
                    required: true,
                ),
                new ToolProperty(
                    name: 'script',
                    type: PropertyType::STRING,
                    description: 'Relative script path (e.g. scripts/generate-mockup.sh)',
                    required: true,
                ),
                new ArrayProperty(
                    name: 'args',
                    description: 'Optional argv strings (no shell metacharacters)',
                    required: false,
                    items: new ToolProperty(
                        name: 'arg',
                        type: PropertyType::STRING,
                        description: 'A single argv string',
                    ),
                ),
            ],
        );

        $this->sandbox ??= app(SkillScriptSandbox::class);
        $this->setCallable(fn (string $name, string $script, ?array $args = null) => $this->run($name, $script, $args ?? []));
    }

    /** @param  list<string>|null  $args */
    public function __invoke(string $name, string $script, ?array $args = null): string
    {
        return $this->run($name, $script, $args ?? []);
    }

    /** @param  list<string>  $args */
    protected function run(string $name, string $script, array $args): string
    {
        $name = trim($name);
        $entry = $this->catalog->get($name);

        if ($entry === null) {
            return "Error: skill [{$name}] is not attached to this agent.";
        }

        $result = ($this->sandbox ?? app(SkillScriptSandbox::class))->run($entry, $script, $args);

        return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: 'Error encoding script result.';
    }
}
