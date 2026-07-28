<?php

namespace DigitalElvis\NeuronAIStudio\Models;

use DigitalElvis\NeuronAIStudio\Support\ConditionalEncryptedCast;
use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Variable extends Model
{
    public const TYPE_CREDENTIAL = 'credential';

    public const TYPE_GENERIC = 'generic';

    public const NAME_PATTERN = '/^[A-Z][A-Z0-9_]*$/';

    protected $table;

    protected $fillable = [
        'name',
        'type',
        'value',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = StudioTables::name('variables');

        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'value' => ConditionalEncryptedCast::class,
        ];
    }

    /**
     * Display value for list UI — never decrypt credentials for listing.
     */
    protected function displayValue(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->type === self::TYPE_CREDENTIAL) {
                return '*****';
            }

            return (string) ($this->attributes['value'] ?? '');
        });
    }

    public function isCredential(): bool
    {
        return $this->type === self::TYPE_CREDENTIAL;
    }

    /**
     * Update type and value safely across encrypt/plaintext boundary.
     */
    public function updateTyped(?string $type, ?string $value, bool $keepValueIfBlank = true): void
    {
        $newType = $type ?? $this->type;
        $oldType = $this->type;

        if ($keepValueIfBlank && ($value === null || $value === '')) {
            if ($newType === $oldType) {
                if ($type !== null) {
                    $this->type = $newType;
                    $this->save();
                }

                return;
            }

            // Type flip with blank value: re-encrypt or decrypt existing plaintext/ciphertext.
            $plain = $this->value;
            $this->type = $newType;
            $this->value = $plain;
            $this->save();

            return;
        }

        if ($newType !== $oldType && $oldType === self::TYPE_CREDENTIAL && $newType === self::TYPE_GENERIC) {
            // Ensure we read decrypted before switching type for storage.
            $plain = $value ?? $this->value;
            $this->type = $newType;
            $this->value = $plain;
            $this->save();

            return;
        }

        $this->type = $newType;
        $this->value = $value;
        $this->save();
    }

    public static function isValidName(string $name): bool
    {
        return (bool) preg_match(self::NAME_PATTERN, $name);
    }

    /**
     * Raw DB value without casting (for ciphertext assertions in tests).
     */
    public function rawValue(): ?string
    {
        return $this->attributes['value'] ?? null;
    }
}
