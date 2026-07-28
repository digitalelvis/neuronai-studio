<?php

namespace DigitalElvis\NeuronAIStudio\Support;

/**
 * Redact values that look like resolved secrets before persisting to traces/SSE.
 */
class SecretScrubber
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function scrub(array $payload): array
    {
        $out = [];

        foreach ($payload as $key => $value) {
            $keyStr = (string) $key;

            if (self::isSensitiveKey($keyStr)) {
                $out[$key] = is_string($value) && $value !== '' ? '*****' : $value;

                continue;
            }

            if (is_array($value)) {
                $out[$key] = self::scrub($value);

                continue;
            }

            $out[$key] = $value;
        }

        return $out;
    }

    protected static function isSensitiveKey(string $key): bool
    {
        $lower = strtolower($key);

        return str_contains($lower, 'api_key')
            || str_contains($lower, 'apikey')
            || str_contains($lower, 'token')
            || str_contains($lower, 'secret')
            || str_contains($lower, 'password')
            || $lower === 'key'
            || str_contains($lower, 'authorization');
    }
}
