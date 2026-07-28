<?php

namespace DigitalElvis\NeuronAIStudio\Support;

use Illuminate\Database\Eloquent\Model;

trait ResolvesOptionalRouteModel
{
    /**
     * Resolve an optional Eloquent route/mount parameter without Livewire
     * implicit binding on null (which 404s create pages).
     *
     * @template T of Model
     *
     * @param  T|int|string|null  $value
     * @param  class-string<T>  $class
     * @return T|null
     */
    protected function resolveOptionalRouteModel(mixed $value, string $class): ?Model
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof $class) {
            return $value;
        }

        return $class::query()->whereKey($value)->firstOrFail();
    }
}
