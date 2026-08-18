<?php

namespace DigitalElvis\NeuronAIStudio\Models;

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use DigitalElvis\NeuronAIStudio\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class McpEndpoint extends Model
{
    use BelongsToTenant;
    protected $table;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'enabled',
        'api_key_hash',
        'api_key_prefix',
        'timeout_seconds',
        'config',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = StudioTables::name('mcp_endpoints');

        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'timeout_seconds' => 'integer',
            'config' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (McpEndpoint $endpoint) {
            if (empty($endpoint->slug)) {
                $endpoint->slug = Str::slug($endpoint->name);
            }
        });
    }

    public function bindings(): HasMany
    {
        return $this->hasMany(McpEndpointBinding::class)->orderBy('sort_order')->orderBy('id');
    }

    public function enabledBindings(): HasMany
    {
        return $this->bindings()->where('enabled', true);
    }

    public function verifyApiKey(string $plainKey): bool
    {
        if ($this->api_key_hash === null || $this->api_key_hash === '') {
            return false;
        }

        return hash_equals($this->api_key_hash, hash('sha256', $plainKey));
    }

    /**
     * Generate a new API key, persist the hash, and return the plaintext once.
     */
    public function rotateApiKey(): string
    {
        $plain = 'nes_'.bin2hex(random_bytes(24));

        $this->forceFill([
            'api_key_hash' => hash('sha256', $plain),
            'api_key_prefix' => substr($plain, 0, 12),
        ])->save();

        return $plain;
    }

    public function hasApiKey(): bool
    {
        return is_string($this->api_key_hash) && $this->api_key_hash !== '';
    }
}
