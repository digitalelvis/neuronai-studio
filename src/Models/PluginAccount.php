<?php

namespace DigitalElvis\NeuronAIStudio\Models;

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PluginAccount extends Model
{
    public const AUTH_NEEDS = 'needs_auth';

    public const AUTH_CONNECTED = 'connected';

    protected $table;

    protected $fillable = [
        'plugin_install_id',
        'label',
        'auth_status',
        'credential_map',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = StudioTables::name('plugin_accounts');

        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'credential_map' => 'array',
        ];
    }

    public function install(): BelongsTo
    {
        return $this->belongsTo(PluginInstall::class, 'plugin_install_id');
    }

    public function agentBindings(): HasMany
    {
        return $this->hasMany(AgentPluginBinding::class);
    }

    public function isConnected(): bool
    {
        return $this->auth_status === self::AUTH_CONNECTED;
    }
}
