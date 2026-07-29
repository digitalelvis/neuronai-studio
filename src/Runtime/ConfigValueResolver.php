<?php

namespace DigitalElvis\NeuronAIStudio\Runtime;

use DigitalElvis\NeuronAIStudio\Exceptions\VariableResolutionException;
use DigitalElvis\NeuronAIStudio\Repositories\VariableRepository;
use Illuminate\Support\Str;

class ConfigValueResolver
{
    public function __construct(
        protected VariableRepository $variables,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function resolveMany(array $values): array
    {
        $resolved = [];

        foreach ($values as $key => $value) {
            $resolved[$key] = $this->resolve($value);
        }

        return $resolved;
    }

    public function resolve(mixed $value): mixed
    {
        if (! is_string($value)) {
            return is_array($value)
                ? array_map(fn ($item) => $this->resolve($item), $value)
                : $value;
        }

        if (str_starts_with($value, 'var:')) {
            $name = Str::after($value, 'var:');

            return $this->variables->resolveValue($name);
        }

        if (str_starts_with($value, 'env:')) {
            return env(Str::after($value, 'env:'), '');
        }

        if (preg_match('/^\{\{\s*env\.([A-Z0-9_]+)\s*\}\}$/', $value, $matches)) {
            return env($matches[1], '');
        }

        return $value;
    }

    /**
     * Resolve fields that historically stored env *names* (token_env, key_env)
     * and may now also store var:NAME or env:VAR.
     */
    public function resolveEnvNameOrVar(?string $stored, ?string $fallbackEnvName = null): ?string
    {
        if ($stored === null || $stored === '') {
            if ($fallbackEnvName === null || $fallbackEnvName === '') {
                return null;
            }

            $value = env($fallbackEnvName);

            return is_string($value) && $value !== '' ? $value : null;
        }

        if (str_starts_with($stored, 'var:') || str_starts_with($stored, 'env:') || preg_match('/^\{\{\s*env\./', $stored)) {
            $resolved = $this->resolve($stored);

            return is_string($resolved) && $resolved !== '' ? $resolved : null;
        }

        $value = env($stored);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function isVariableRef(string $value): bool
    {
        return str_starts_with($value, 'var:');
    }

    public static function variableNameFromRef(string $value): ?string
    {
        if (! self::isVariableRef($value)) {
            return null;
        }

        return Str::after($value, 'var:') ?: null;
    }
}
