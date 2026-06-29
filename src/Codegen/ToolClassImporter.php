<?php

namespace DigitalElvis\NeuronAIStudio\Codegen;

use Illuminate\Support\Str;
use ReflectionClass;

class ToolClassImporter
{
    /** @return array<string, mixed>|null */
    public function fromClass(string $class): ?array
    {
        if (! class_exists($class)) {
            return null;
        }

        $reflection = new ReflectionClass($class);
        $file = $reflection->getFileName();

        if ($file === false || ! is_readable($file)) {
            return null;
        }

        $content = file_get_contents($file);

        if ($content === false) {
            return null;
        }

        $toolName = $this->match($content, "/parent::__construct\s*\(\s*['\"]([^'\"]+)['\"]/") ?? Str::snake($reflection->getShortName());
        $description = $this->match($content, "/parent::__construct\s*\(\s*['\"][^'\"]+['\"]\s*,\s*['\"]([^'\"]*)['\"]/") ?? '';

        return [
            'class_name' => $reflection->getShortName(),
            'class_path' => $class,
            'tool_name' => $toolName,
            'name' => Str::headline(str_replace('Tool', '', $reflection->getShortName())),
            'description' => $description,
            'input_schema' => $this->parseProperties($content),
            'invoke_body' => $this->parseInvokeBody($content),
        ];
    }

    /** @return array<int, array{name: string, type: string, description: string, required: bool}> */
    protected function parseProperties(string $content): array
    {
        $properties = [];

        if (! preg_match_all(
            "/new ToolProperty\s*\(\s*name:\s*['\"]([^'\"]+)['\"]\s*,\s*type:\s*PropertyType::(\w+)\s*,\s*description:\s*['\"]([^'\"]*)['\"]\s*,\s*required:\s*(true|false)/s",
            $content,
            $matches,
            PREG_SET_ORDER
        )) {
            return $properties;
        }

        foreach ($matches as $match) {
            $properties[] = [
                'name' => $match[1],
                'type' => strtolower($match[2]),
                'description' => $match[3],
                'required' => $match[4] === 'true',
            ];
        }

        return $properties;
    }

    protected function parseInvokeBody(string $content): string
    {
        if (! preg_match('/function __invoke\s*\([^)]*\)[^{]*\{([\s\S]*?)\n    \}/', $content, $match)) {
            return "return 'Executed';";
        }

        $body = preg_replace('/^\s{8}/m', '', trim($match[1])) ?? trim($match[1]);

        return trim($body);
    }

    protected function match(string $content, string $pattern): ?string
    {
        if (! preg_match($pattern, $content, $matches)) {
            return null;
        }

        return $matches[1];
    }
}
