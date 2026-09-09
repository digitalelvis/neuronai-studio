<?php

namespace DigitalElvis\NeuronAIStudio\Models;

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PluginPackage extends Model
{
    protected $table;

    protected $fillable = [
        'slug',
        'version',
        'content_hash',
        'name',
        'description',
        'manifest',
        'mcp_templates',
        'source',
        'source_url',
        'source_path',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = StudioTables::name('plugin_packages');

        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'manifest' => 'array',
            'mcp_templates' => 'array',
        ];
    }

    public function skills(): HasMany
    {
        return $this->hasMany(PluginPackageSkill::class);
    }

    public function installs(): HasMany
    {
        return $this->hasMany(PluginInstall::class, 'package_id');
    }

    public function skillRef(string $skillSlug): string
    {
        return PluginPackageSkill::bindingRefFor($this->id, $skillSlug);
    }
}
