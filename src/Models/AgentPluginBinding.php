<?php

namespace DigitalElvis\NeuronAIStudio\Models;

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentPluginBinding extends Model
{
    protected $table;

    protected $fillable = [
        'agent_definition_id',
        'plugin_install_id',
        'plugin_account_id',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = StudioTables::name('agent_plugin_bindings');

        parent::__construct($attributes);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(AgentDefinition::class, 'agent_definition_id');
    }

    public function install(): BelongsTo
    {
        return $this->belongsTo(PluginInstall::class, 'plugin_install_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(PluginAccount::class, 'plugin_account_id');
    }
}
