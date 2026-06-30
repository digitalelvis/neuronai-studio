<?php

namespace DigitalElvis\NeuronAIStudio\Models;

use Illuminate\Database\Eloquent\Model;

class ToolDefinition extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type',
        'description',
        'input_schema',
        'config',
        'metadata',
    ];

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
