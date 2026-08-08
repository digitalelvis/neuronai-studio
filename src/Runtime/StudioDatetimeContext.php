<?php

namespace DigitalElvis\NeuronAIStudio\Runtime;

use Carbon\Carbon;
use DateTimeZone;
use Throwable;

/**
 * Studio system datetime context for workflow state and prompt interpolation.
 */
final class StudioDatetimeContext
{
    public const KEY_NOW = '__studio_now';

    public const KEY_TIMEZONE = '__studio_timezone';

    public const KEY_LOCALE = '__studio_locale';

    /**
     * Build the three system keys from optional overrides (caller initial state).
     *
     * @param  array<string, mixed>  $initialState
     * @return array{__studio_now: string, __studio_timezone: string, __studio_locale: string}
     */
    public static function forState(array $initialState = []): array
    {
        $timezone = self::stringOrNull($initialState[self::KEY_TIMEZONE] ?? null);
        $locale = self::stringOrNull($initialState[self::KEY_LOCALE] ?? null);

        return self::defaults($timezone, $locale);
    }

    /**
     * @return array{__studio_now: string, __studio_timezone: string, __studio_locale: string}
     */
    public static function defaults(?string $timezone = null, ?string $locale = null): array
    {
        $resolvedTimezone = self::resolveTimezone($timezone);
        $resolvedLocale = self::resolveLocale($locale);

        return [
            self::KEY_NOW => self::nowIso($resolvedTimezone),
            self::KEY_TIMEZONE => $resolvedTimezone,
            self::KEY_LOCALE => $resolvedLocale,
        ];
    }

    public static function nowIso(?string $timezone = null): string
    {
        $tz = self::resolveTimezone($timezone);

        return Carbon::now($tz)->toIso8601String();
    }

    public static function resolveTimezone(?string $timezone): string
    {
        $fallback = (string) config('app.timezone', 'UTC');
        if ($fallback === '') {
            $fallback = 'UTC';
        }

        if ($timezone === null || trim($timezone) === '') {
            return self::isValidTimezone($fallback) ? $fallback : 'UTC';
        }

        $candidate = trim($timezone);

        return self::isValidTimezone($candidate) ? $candidate : (self::isValidTimezone($fallback) ? $fallback : 'UTC');
    }

    public static function resolveLocale(?string $locale): string
    {
        $fallback = (string) config('app.locale', 'en');
        if ($fallback === '') {
            $fallback = 'en';
        }

        if ($locale === null || trim($locale) === '') {
            return $fallback;
        }

        return trim($locale);
    }

    protected static function isValidTimezone(string $timezone): bool
    {
        try {
            new DateTimeZone($timezone);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    protected static function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
