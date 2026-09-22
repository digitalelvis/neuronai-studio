<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Routing;

use InvalidArgumentException;

class RoutingConfig
{
    /** @var list<string> */
    public const TIERS = ['easy', 'medium', 'hard'];

    /**
     * @param  array<string, array{provider: string, model: string, api_key: ?string}>  $tiers
     */
    public function __construct(
        public readonly ?string $classifierApiKey,
        public readonly float $easyMax,
        public readonly float $mediumMax,
        public readonly array $tiers,
    ) {}

    /**
     * @param  array<string, mixed>|null  $raw
     */
    public static function fromStored(?array $raw): ?self
    {
        if (! is_array($raw) || ($raw['enabled'] ?? false) !== true) {
            return null;
        }

        return self::parse($raw);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function normalizeForStorage(mixed $raw): ?array
    {
        if (! is_array($raw) || ($raw['enabled'] ?? false) !== true) {
            return null;
        }

        $config = self::parse($raw);
        $tiers = [];
        foreach ($config->tiers as $name => $tier) {
            $tiers[$name] = [
                'provider' => $tier['provider'],
                'model' => $tier['model'],
                'api_key' => $tier['api_key'],
            ];
        }

        return [
            'enabled' => true,
            'classifier_api_key' => $config->classifierApiKey,
            'easy_max' => $config->easyMax,
            'medium_max' => $config->mediumMax,
            'tiers' => $tiers,
        ];
    }

    public function defaultTier(): string
    {
        foreach (['hard', 'medium', 'easy'] as $tier) {
            if (isset($this->tiers[$tier])) {
                return $tier;
            }
        }

        throw new InvalidArgumentException('Difficulty routing requires at least one tier.');
    }

    public function has(string $tier): bool
    {
        return isset($this->tiers[$tier]);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    protected static function parse(array $raw): self
    {
        $easyMax = self::threshold($raw['easy_max'] ?? 0.33, 'easy_max');
        $mediumMax = self::threshold($raw['medium_max'] ?? 0.70, 'medium_max');

        $tiers = [];
        $rawTiers = is_array($raw['tiers'] ?? null) ? $raw['tiers'] : [];
        foreach (self::TIERS as $name) {
            $tier = $rawTiers[$name] ?? null;
            if (! is_array($tier)) {
                continue;
            }

            $provider = trim((string) ($tier['provider'] ?? ''));
            $model = trim((string) ($tier['model'] ?? ''));
            if ($provider === '' || $model === '') {
                throw new InvalidArgumentException(
                    "Difficulty routing tier [{$name}] requires a provider and a model."
                );
            }

            $apiKey = $tier['api_key'] ?? null;
            $apiKey = is_string($apiKey) && trim($apiKey) !== '' ? trim($apiKey) : null;

            $tiers[$name] = [
                'provider' => $provider,
                'model' => $model,
                'api_key' => $apiKey,
            ];
        }

        if ($tiers === []) {
            throw new InvalidArgumentException('Difficulty routing requires at least one tier (easy, medium, or hard).');
        }

        if (isset($tiers['easy'], $tiers['medium']) && $easyMax >= $mediumMax) {
            throw new InvalidArgumentException('Difficulty routing easy_max must be below medium_max.');
        }

        $classifierKey = $raw['classifier_api_key'] ?? null;
        $classifierKey = is_string($classifierKey) && trim($classifierKey) !== '' ? trim($classifierKey) : null;

        return new self($classifierKey, $easyMax, $mediumMax, $tiers);
    }

    protected static function threshold(mixed $value, string $name): float
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException("Difficulty routing {$name} must be a number between 0 and 1.");
        }

        $number = (float) $value;
        if (! is_finite($number) || $number < 0 || $number > 1) {
            throw new InvalidArgumentException("Difficulty routing {$name} must be a number between 0 and 1.");
        }

        return $number;
    }
}
