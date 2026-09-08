<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Skills;

class SkillPathPolicy
{
    /** @var list<string> */
    public const ALLOWED_PREFIXES = [
        'references/',
        'scripts/',
        'assets/',
    ];

    /** @var list<string> */
    public const EXECUTABLE_EXTENSIONS = [
        'sh',
        'py',
        'js',
    ];

    public function isAllowed(string $path): bool
    {
        $path = $this->normalize($path);

        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/')) {
            return false;
        }

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function isExecutable(string $path): bool
    {
        $path = $this->normalize($path);

        if (! str_starts_with($path, 'scripts/') || ! $this->isAllowed($path)) {
            return false;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $allowed = config('neuronai-studio.skills.execution.extensions', self::EXECUTABLE_EXTENSIONS);

        return in_array($extension, $allowed, true);
    }

    public function isReadable(string $path): bool
    {
        return $this->isAllowed($path);
    }

    public function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');

        while (str_contains($path, '//')) {
            $path = str_replace('//', '/', $path);
        }

        return $path;
    }

    /** @return list<string> */
    public function allowedPrefixes(): array
    {
        return self::ALLOWED_PREFIXES;
    }
}
