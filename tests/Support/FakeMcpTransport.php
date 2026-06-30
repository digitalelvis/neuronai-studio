<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Support;

use NeuronAI\MCP\McpTransportInterface;

class FakeMcpTransport implements McpTransportInterface
{
    /** @param  array<int, array<string, mixed>>  $tools */
    public function __construct(
        protected array $tools = [],
    ) {}

    /** @var array<string, mixed>|null */
    protected ?array $lastRequest = null;

    public function connect(): void {}

    public function send(array $data): void
    {
        $this->lastRequest = $data;
    }

    public function receive(): array
    {
        $data = $this->lastRequest ?? [];
        $id = $data['id'] ?? null;

        if (($data['method'] ?? '') === 'initialize') {
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'protocolVersion' => '2024-11-05',
                    'capabilities' => [],
                    'serverInfo' => ['name' => 'fake', 'version' => '1.0.0'],
                ],
            ];
        }

        if (($data['method'] ?? '') === 'tools/list') {
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'tools' => $this->tools,
                ],
            ];
        }

        if (($data['method'] ?? '') === 'tools/call') {
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => [
                    'content' => [
                        ['type' => 'text', 'text' => 'ok'],
                    ],
                ],
            ];
        }

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [],
        ];
    }

    public function disconnect(): void {}
}
