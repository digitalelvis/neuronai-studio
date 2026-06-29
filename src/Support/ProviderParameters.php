<?php

namespace DigitalElvis\NeuronAIStudio\Support;

class ProviderParameters
{
    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    public static function merge(string $provider, array $base, array $override): array
    {
        $normalized = self::normalize($provider, $override);

        if ($normalized === []) {
            return $base;
        }

        return array_replace_recursive($base, $normalized);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public static function normalize(string $provider, array $parameters): array
    {
        $parameters = array_filter(
            $parameters,
            static fn ($value) => $value !== null && $value !== '',
        );

        if ($parameters === []) {
            return [];
        }

        return match ($provider) {
            'gemini' => self::forGemini($parameters),
            'anthropic' => self::forAnthropic($parameters),
            default => self::forOpenAiLike($parameters),
        };
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    protected static function forOpenAiLike(array $parameters): array
    {
        $mapped = [];

        if (array_key_exists('temperature', $parameters)) {
            $mapped['temperature'] = (float) $parameters['temperature'];
        }

        if (array_key_exists('top_p', $parameters)) {
            $mapped['top_p'] = (float) $parameters['top_p'];
        }

        if (array_key_exists('max_tokens', $parameters)) {
            $mapped['max_tokens'] = (int) $parameters['max_tokens'];
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    protected static function forGemini(array $parameters): array
    {
        $generationConfig = [];

        if (array_key_exists('temperature', $parameters)) {
            $generationConfig['temperature'] = (float) $parameters['temperature'];
        }

        if (array_key_exists('top_p', $parameters)) {
            $generationConfig['topP'] = (float) $parameters['top_p'];
        }

        if (array_key_exists('max_tokens', $parameters)) {
            $generationConfig['maxOutputTokens'] = (int) $parameters['max_tokens'];
        }

        if ($generationConfig === []) {
            return [];
        }

        return ['generationConfig' => $generationConfig];
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    protected static function forAnthropic(array $parameters): array
    {
        $mapped = [];

        if (array_key_exists('temperature', $parameters)) {
            $mapped['temperature'] = (float) $parameters['temperature'];
        }

        if (array_key_exists('top_p', $parameters)) {
            $mapped['top_p'] = (float) $parameters['top_p'];
        }

        if (array_key_exists('max_tokens', $parameters)) {
            $mapped['max_tokens'] = (int) $parameters['max_tokens'];
        }

        return $mapped;
    }
}
