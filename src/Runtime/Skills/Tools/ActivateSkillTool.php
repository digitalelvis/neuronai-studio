<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Skills\Tools;

use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillCatalog;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

class ActivateSkillTool extends Tool
{
    public function __construct(
        protected SkillCatalog $catalog,
    ) {
        parent::__construct(
            name: 'activate_skill',
            description: 'Load the full instructions for an attached agent skill by name. Call when the user task matches a skill description from Available skills.',
            properties: [
                new ToolProperty(
                    name: 'name',
                    type: PropertyType::STRING,
                    description: 'The skill name (slug) from Available skills',
                    required: true,
                ),
            ],
        );

        $this->setCallable(fn (string $name) => $this->activate($name));
    }

    public function __invoke(string $name): string
    {
        return $this->activate($name);
    }

    protected function activate(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return 'Error: skill name is required.';
        }

        $entry = $this->catalog->get($name);

        if ($entry === null) {
            $available = implode(', ', array_map(
                fn ($item) => $item->name,
                $this->catalog->entries(),
            ));

            return "Error: skill [{$name}] is not attached to this agent. Available: {$available}";
        }

        $body = trim($entry->definition->body());

        if ($body === '') {
            return "Skill [{$name}] has no instruction body.";
        }

        $files = $entry->definition->files();
        $scripts = [];
        $references = [];
        $assets = [];

        foreach (array_keys($files) as $path) {
            if (str_starts_with($path, 'scripts/')) {
                $scripts[] = $path;
            } elseif (str_starts_with($path, 'references/')) {
                $references[] = $path;
            } elseif (str_starts_with($path, 'assets/')) {
                $assets[] = $path;
            }
        }

        $hints = [];
        if ($scripts !== []) {
            $hints[] = 'Scripts (run_skill_script): '.implode(', ', $scripts);
        }
        if ($references !== []) {
            $hints[] = 'References (read_skill_file): '.implode(', ', $references);
        }
        if ($assets !== []) {
            $hints[] = 'Assets (read_skill_file): '.implode(', ', $assets);
        }

        $resourceHint = $hints === [] ? '' : "\n\n".implode("\n", $hints);

        return "# Skill: {$name}\n\n{$body}{$resourceHint}";
    }
}
