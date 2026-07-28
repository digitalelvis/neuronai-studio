<?php

namespace DigitalElvis\NeuronAIStudio\Support;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Encrypts value when the model type is credential; stores plaintext for generic.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class ConditionalEncryptedCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (($attributes['type'] ?? $model->getAttribute('type')) === 'credential') {
            return Crypt::decryptString($value);
        }

        return (string) $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $type = $attributes['type'] ?? $model->getAttribute('type');

        if ($type === 'credential') {
            return Crypt::encryptString((string) $value);
        }

        return (string) $value;
    }
}
