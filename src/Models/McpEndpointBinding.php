<?php

namespace DigitalElvis\NeuronAIStudio\Models;

use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McpEndpointBinding extends Model
{
    public const KIND_TOOL = 'tool';

    public const KIND_TOOLKIT = 'toolkit';

    public const KIND_AGENT = 'agent';

    public const KIND_WORKFLOW = 'workflow';

    public const KINDS = [
        self::KIND_TOOL,
        self::KIND_TOOLKIT,
        self::KIND_AGENT,
        self::KIND_WORKFLOW,
    ];

    protected $table;

    protected $fillable = [
        'mcp_endpoint_id',
        'kind',
        'ref',
        'tool_name',
        'tool_description',
        'only',
        'exclude',
        'enabled',
        'sort_order',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = StudioTables::name('mcp_endpoint_bindings');

        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'only' => 'array',
            'exclude' => 'array',
            'enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(McpEndpoint::class, 'mcp_endpoint_id');
    }
}
