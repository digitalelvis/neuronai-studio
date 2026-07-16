<?php

namespace DigitalElvis\NeuronAIStudio\Usage;

class UsageCostEstimator
{
    public function currency(): string
    {
        $currency = config('neuronai-studio.usage.currency');

        return is_string($currency) && $currency !== '' ? $currency : 'USD';
    }

    /**
     * @return array{prompt_per_1k: float, completion_per_1k: float}|null
     */
    public function rate(?string $provider, ?string $model): ?array
    {
        if ($provider === null || $provider === '' || $model === null || $model === '') {
            return null;
        }

        $entry = config("neuronai-studio.usage.pricing.{$provider}.{$model}");

        if (! is_array($entry)) {
            return null;
        }

        return [
            'prompt_per_1k' => $this->coerceRate($entry['prompt_per_1k'] ?? null),
            'completion_per_1k' => $this->coerceRate($entry['completion_per_1k'] ?? null),
        ];
    }

    public function estimate(
        ?string $provider,
        ?string $model,
        int $promptTokens,
        int $completionTokens,
    ): string {
        $rates = $this->rate($provider, $model);

        if ($rates === null) {
            return '0.000000';
        }

        $promptTokens = max(0, $promptTokens);
        $completionTokens = max(0, $completionTokens);

        $cost = ($promptTokens / 1000) * $rates['prompt_per_1k']
            + ($completionTokens / 1000) * $rates['completion_per_1k'];

        if (! is_finite($cost) || $cost < 0) {
            return '0.000000';
        }

        return number_format($cost, 6, '.', '');
    }

    private function coerceRate(mixed $value): float
    {
        if (! is_numeric($value)) {
            return 0.0;
        }

        $rate = (float) $value;

        if (! is_finite($rate) || $rate < 0) {
            return 0.0;
        }

        return $rate;
    }
}
