<?php

namespace ElvisLopesDigital\NeuronAIStudio\Tests;

use ElvisLopesDigital\NeuronAIStudio\MCP\McpStdioTransport;
use PHPUnit\Framework\TestCase;

class McpStdioTransportTest extends TestCase
{
    public function test_extracts_newline_delimited_json_line(): void
    {
        $transport = new McpStdioTransport(['command' => 'echo']);

        $payload = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['tools' => []],
        ], JSON_THROW_ON_ERROR);

        $reflection = new \ReflectionClass($transport);
        $buffer = $reflection->getProperty('readBuffer');
        $buffer->setAccessible(true);
        $buffer->setValue($transport, $payload."\n");

        $method = $reflection->getMethod('tryExtractLine');
        $method->setAccessible(true);

        $this->assertSame($payload, $method->invoke($transport));
    }

    public function test_extracts_content_length_framed_message(): void
    {
        $transport = new McpStdioTransport([
            'command' => 'echo',
            'stdio_framing' => 'content-length',
        ]);

        $payload = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['tools' => []],
        ], JSON_THROW_ON_ERROR);

        $reflection = new \ReflectionClass($transport);
        $buffer = $reflection->getProperty('readBuffer');
        $buffer->setAccessible(true);
        $buffer->setValue($transport, 'Content-Length: '.strlen($payload)."\r\n\r\n".$payload);

        $method = $reflection->getMethod('tryExtractContentLengthMessage');
        $method->setAccessible(true);

        $this->assertSame($payload, $method->invoke($transport));
    }
}
