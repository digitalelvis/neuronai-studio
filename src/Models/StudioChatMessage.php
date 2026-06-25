<?php

namespace ElvisLopesDigital\NeuronAIStudio\Models;

use ElvisLopesDigital\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Eloquent\Model;

class StudioChatMessage extends Model
{
    protected $table;

    protected $fillable = [
        'thread_id',
        'role',
        'content',
        'meta',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = StudioTables::name('chat_messages');

        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'meta' => 'array',
        ];
    }
}
