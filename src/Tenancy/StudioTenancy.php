<?php

namespace DigitalElvis\NeuronAIStudio\Tenancy;

final class StudioTenancy
{
    private static ?string $override = null;

    private static bool $central = false;

    private static int $withoutScope = 0;

    public static function enabled(): bool
    {
        return (bool) config('neuronai-studio.tenancy.enabled', false);
    }

    public static function driver(): string
    {
        $driver = (string) config('neuronai-studio.tenancy.driver', 'shared');

        return $driver === 'database' ? 'database' : 'shared';
    }

    public static function scopesShared(): bool
    {
        return self::enabled() && self::driver() === 'shared';
    }

    public static function id(): ?string
    {
        if (self::$central) {
            return null;
        }

        if (self::$override !== null) {
            return self::$override;
        }

        $id = app(TenantResolver::class)->id();

        if ($id === null || $id === '') {
            return null;
        }

        return (string) $id;
    }

    public static function isCentral(): bool
    {
        return self::$central;
    }

    public static function hasTenant(): bool
    {
        return self::id() !== null;
    }

    public static function isAbsent(): bool
    {
        return self::enabled() && ! self::$central && self::id() === null;
    }

    public static function skipsScope(): bool
    {
        return self::$withoutScope > 0;
    }

    /** @internal Reset nested context — tests only. */
    public static function reset(): void
    {
        self::$override = null;
        self::$central = false;
        self::$withoutScope = 0;
    }

    /**
     * Run $callback with the given tenant. A null id enters central (globals only).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function run(?string $id, callable $callback): mixed
    {
        $previousOverride = self::$override;
        $previousCentral = self::$central;

        if ($id === null || $id === '') {
            self::$central = true;
            self::$override = null;
        } else {
            self::$central = false;
            self::$override = $id;
        }

        try {
            return $callback();
        } finally {
            self::$override = $previousOverride;
            self::$central = $previousCentral;
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function central(callable $callback): mixed
    {
        return self::run(null, $callback);
    }

    /**
     * Bypass TenantScope for bootstrap lookups (queue jobs).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withoutScope(callable $callback): mixed
    {
        self::$withoutScope++;

        try {
            return $callback();
        } finally {
            self::$withoutScope--;
        }
    }
}
