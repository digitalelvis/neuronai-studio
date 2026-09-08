<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Skills;

use InvalidArgumentException;

class SkillParser
{
    /**
     * @return array{slug: string, description: string, license: ?string, compatibility: ?string, metadata: array<string, string>, body: string}
     */
    public function parse(string $markdown): array
    {
        $markdown = ltrim($markdown);

        if (! str_starts_with($markdown, "---\n") && ! str_starts_with($markdown, "---\r\n")) {
            throw new InvalidArgumentException('SKILL.md must start with YAML frontmatter (---).');
        }

        $end = strpos($markdown, "\n---", 3);
        if ($end === false) {
            throw new InvalidArgumentException('SKILL.md frontmatter is not closed with ---.');
        }

        $frontmatter = substr($markdown, 4, $end - 4);
        $body = ltrim(substr($markdown, $end + 4));

        $meta = $this->parseFrontmatter($frontmatter);

        $slug = (string) ($meta['name'] ?? '');
        $description = (string) ($meta['description'] ?? '');

        $this->validateSlug($slug);
        $this->validateDescription($description);

        $metadata = $this->normalizeMetadata($meta['metadata'] ?? []);

        foreach (['version', 'allowed-tools', 'category'] as $extraKey) {
            if (isset($meta[$extraKey]) && is_string($meta[$extraKey]) && $meta[$extraKey] !== '') {
                $metadata[$extraKey] = $meta[$extraKey];
            }
        }

        return [
            'slug' => $slug,
            'description' => $description,
            'license' => isset($meta['license']) ? (string) $meta['license'] : null,
            'compatibility' => isset($meta['compatibility']) ? (string) $meta['compatibility'] : null,
            'metadata' => $metadata,
            'body' => $body,
            'category' => isset($meta['category']) ? (string) $meta['category'] : ($metadata['category'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function validateFields(string $slug, string $description): void
    {
        $this->validateSlug($slug);
        $this->validateDescription($description);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, string>
     */
    public function buildFrontmatter(array $meta): string
    {
        $lines = ['---'];

        foreach ($meta as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if ($key === 'metadata' && is_array($value)) {
                $lines[] = 'metadata:';
                foreach ($value as $mk => $mv) {
                    $lines[] = '  '.$mk.': "'.str_replace('"', '\\"', (string) $mv).'"';
                }

                continue;
            }

            $lines[] = $key.': "'.str_replace('"', '\\"', (string) $value).'"';
        }

        $lines[] = '---';

        return implode("\n", $lines);
    }

    /** @return array<string, string> */
    protected function parseFrontmatter(string $yaml): array
    {
        $result = [];
        $lines = preg_split('/\r\n|\r|\n/', $yaml) ?: [];
        $metadataBlock = false;
        $metadata = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                continue;
            }

            if ($metadataBlock) {
                if (preg_match('/^\s{2,}(\w[\w-]*)\s*:\s*(.+)$/', $line, $matches)) {
                    $metadata[$matches[1]] = trim($matches[2], " \t\"'");

                    continue;
                }

                $metadataBlock = false;
            }

            if (str_starts_with($trimmed, 'metadata:')) {
                $metadataBlock = true;

                continue;
            }

            if (! str_contains($trimmed, ':')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode(':', $trimmed, 2));
            $result[$key] = trim($value, " \t\"'");
        }

        if ($metadata !== []) {
            $result['metadata'] = $metadata;
        }

        return $result;
    }

    /** @param  mixed  $metadata */
    protected function normalizeMetadata(mixed $metadata): array
    {
        if (! is_array($metadata)) {
            return [];
        }

        $normalized = [];

        foreach ($metadata as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $normalized[$key] = (string) $value;
        }

        return $normalized;
    }

    protected function validateSlug(string $slug): void
    {
        if ($slug === '') {
            throw new InvalidArgumentException('Skill name is required.');
        }

        if (strlen($slug) > 64) {
            throw new InvalidArgumentException('Skill name must be at most 64 characters.');
        }

        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw new InvalidArgumentException('Skill name must be lowercase alphanumeric with single hyphens.');
        }
    }

    protected function validateDescription(string $description): void
    {
        if ($description === '') {
            throw new InvalidArgumentException('Skill description is required.');
        }

        if (strlen($description) > 1024) {
            throw new InvalidArgumentException('Skill description must be at most 1024 characters.');
        }
    }
}
