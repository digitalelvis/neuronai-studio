<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Skills;

class SkillFileReader
{
    public function __construct(
        protected SkillPathPolicy $pathPolicy,
    ) {}

    public function read(SkillCatalog $catalog, string $name, string $path): string
    {
        $name = trim($name);
        $path = $this->pathPolicy->normalize($path);

        if ($name === '' || $path === '') {
            return 'Error: skill name and path are required.';
        }

        if (! $this->pathPolicy->isReadable($path)) {
            $allowed = implode(', ', $this->pathPolicy->allowedPrefixes());

            return "Error: path is not allowed. Allowed prefixes: {$allowed}";
        }

        $entry = $catalog->get($name);

        if ($entry === null) {
            return "Error: skill [{$name}] is not attached to this agent.";
        }

        $files = $entry->definition->files();

        if (! array_key_exists($path, $files)) {
            $available = implode(', ', array_keys($files));

            return $available === ''
                ? "Error: skill [{$name}] has no resource files."
                : "Error: path [{$path}] not found. Available: {$available}";
        }

        $contents = $files[$path];

        if (str_starts_with($contents, 'base64:')) {
            return '[binary file base64] '.substr($contents, 7, 120).(strlen($contents) > 127 ? '…' : '');
        }

        return $contents;
    }
}
