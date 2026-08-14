<?php

namespace DigitalElvis\NeuronAIStudio\Integration;

/**
 * Minimal RFC 6902 JSON Patch generator for AG-UI STATE_DELTA.
 */
final class JsonPatch
{
    /**
     * @param  array<string, mixed>  $from
     * @param  array<string, mixed>  $to
     * @return list<array{op: string, path: string, value?: mixed}>
     */
    public static function diff(array $from, array $to): array
    {
        return self::walk($from, $to, '');
    }

    /**
     * @param  mixed  $from
     * @param  mixed  $to
     * @return list<array{op: string, path: string, value?: mixed}>
     */
    protected static function walk(mixed $from, mixed $to, string $path): array
    {
        if ($from === $to) {
            return [];
        }

        if (self::isObjectMap($from) && self::isObjectMap($to)) {
            $ops = [];

            foreach ($to as $key => $value) {
                $child = self::pointer($path, (string) $key);

                if (! array_key_exists($key, $from)) {
                    $ops[] = ['op' => 'add', 'path' => $child, 'value' => $value];

                    continue;
                }

                $ops = array_merge($ops, self::walk($from[$key], $value, $child));
            }

            foreach ($from as $key => $value) {
                if (! array_key_exists($key, $to)) {
                    $ops[] = ['op' => 'remove', 'path' => self::pointer($path, (string) $key)];
                }
            }

            return $ops;
        }

        $replacePath = $path === '' ? '' : $path;

        if ($from === null && $path !== '') {
            return [['op' => 'add', 'path' => $replacePath, 'value' => $to]];
        }

        return [['op' => 'replace', 'path' => $replacePath, 'value' => $to]];
    }

    protected static function pointer(string $parent, string $key): string
    {
        $escaped = str_replace(['~', '/'], ['~0', '~1'], $key);

        return ($parent === '' ? '' : $parent).'/'.$escaped;
    }

    /**
     * @param  mixed  $value
     */
    protected static function isObjectMap(mixed $value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        if ($value === []) {
            return true;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }
}
