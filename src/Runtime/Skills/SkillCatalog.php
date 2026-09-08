<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Skills;

class SkillCatalog
{
    /** @param  list<SkillCatalogEntry>  $entries */
    public function __construct(
        protected array $entries = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    /** @return list<SkillCatalogEntry> */
    public function entries(): array
    {
        return $this->entries;
    }

    public function has(string $name): bool
    {
        foreach ($this->entries as $entry) {
            if ($entry->name === $name) {
                return true;
            }
        }

        return false;
    }

    public function get(string $name): ?SkillCatalogEntry
    {
        foreach ($this->entries as $entry) {
            if ($entry->name === $name) {
                return $entry;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function resourcePaths(string $name): array
    {
        $entry = $this->get($name);

        if ($entry === null) {
            return [];
        }

        return array_keys($entry->definition->files());
    }
}
