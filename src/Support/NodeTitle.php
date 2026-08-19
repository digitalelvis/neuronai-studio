<?php

namespace DigitalElvis\NeuronAIStudio\Support;

use Illuminate\Support\Str;

final class NodeTitle
{
    public const MAX_LENGTH = 80;

    public static function normalize(?string $title): ?string
    {
        if ($title === null) {
            return null;
        }

        $trimmed = trim($title);

        return $trimmed === '' ? null : $trimmed;
    }

    public static function uniquenessKey(?string $title): ?string
    {
        $normalized = self::normalize($title);

        return $normalized === null ? null : mb_strtolower($normalized);
    }

    public static function slug(?string $title, string $type, string $id): string
    {
        $normalized = self::normalize($title);

        if ($normalized === null) {
            return Str::studly($id);
        }

        $slug = Str::studly(Str::ascii($normalized));

        if (! self::isValidSlug($slug)) {
            $slug = Str::studly($type).$slug;
        }

        if (! self::isValidSlug($slug)) {
            $slug = Str::studly($id);
        }

        return $slug;
    }

    protected static function isValidSlug(string $slug): bool
    {
        return $slug !== '' && preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $slug) === 1;
    }

    /**
     * @param  array<int, string|null>  $existingTitles
     */
    public static function uniqueDefault(string $base, array $existingTitles): string
    {
        $existingKeys = array_values(array_filter(array_map(
            fn ($title) => self::uniquenessKey(is_string($title) ? $title : null),
            $existingTitles,
        )));

        $candidate = trim($base);

        if (! in_array(self::uniquenessKey($candidate), $existingKeys, true)) {
            return $candidate;
        }

        $suffix = 2;

        while (in_array(self::uniquenessKey("{$candidate} {$suffix}"), $existingKeys, true)) {
            $suffix++;
        }

        return "{$candidate} {$suffix}";
    }
}
