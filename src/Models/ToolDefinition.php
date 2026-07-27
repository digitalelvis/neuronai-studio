<?php

namespace DigitalElvis\NeuronAIStudio\Models;

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Eloquent\Model;

class ToolDefinition extends Model
{
    protected $table;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'description',
        'input_schema',
        'config',
        'metadata',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = StudioTables::name('tool_definitions');

        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'input_schema' => 'array',
            'config' => 'array',
            'metadata' => 'array',
        ];
    }

    public function bindingRef(): string
    {
        return "tool:db:{$this->id}";
    }
}
