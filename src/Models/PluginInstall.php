<?php

namespace DigitalElvis\NeuronAIStudio\Models;

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use DigitalElvis\NeuronAIStudio\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PluginInstall extends Model
{
    use BelongsToTenant;

    public const STATUS_INSTALLED = 'installed';

    public const STATUS_UNINSTALLED = 'uninstalled';

    protected $table;

    protected $fillable = [
        'slug',
        'name',
        'version',
        'description',
        'manifest',
        'source',
        'source_url',
        'source_path',
        'status',
        'materialized',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = StudioTables::name('plugin_installs');

        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'manifest' => 'array',
            'materialized' => 'array',
        ];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(PluginAccount::class);
    }

    public function agentBindings(): HasMany
    {
        return $this->hasMany(AgentPluginBinding::class);
    }

    public function isInstalled(): bool
    {
        return $this->status === self::STATUS_INSTALLED;
    }

    /** @return array<int, int> */
    public function materializedSkillIds(): array
    {
        $ids = $this->materialized['skill_ids'] ?? [];

        return is_array($ids) ? array_values(array_map('intval', $ids)) : [];
    }

    /** @return array<int, string> */
    public function materializedMcpSlugs(): array
    {
        $slugs = $this->materialized['mcp_slugs'] ?? [];

        return is_array($slugs) ? array_values(array_map('strval', $slugs)) : [];
    }
}
