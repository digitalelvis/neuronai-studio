<?php

namespace ElvisLopesDigital\NeuronAIStudio\MCP;

use NeuronAI\MCP\McpException;
use NeuronAI\MCP\McpTransportInterface;

/**
 * MCP stdio transport compatible with official MCP Node servers.
 *
 * Handles newline-delimited JSON-RPC (used by @modelcontextprotocol/server-filesystem)
 * and optional Content-Length framing via config `stdio_framing`.
 *
 * Waits for the server process to become ready before the first request, avoiding
 * npx/npm startup noise on stdout that breaks JSON parsing.
 */
class McpStdioTransport implements McpTransportInterface
{
    /** @var null|resource */
    private mixed $process = null;

    /** @var null|array<int, resource> */
    private ?array $pipes = null;

    private string $readBuffer = '';

    private bool $ready = false;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(protected array $config) {}

    public function connect(): void
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $command = $this->config['command'] ?? null;
        if (! is_string($command) || $command === '') {
            throw new McpException('Stdio transport requires a command.');
        }

        $args = $this->config['args'] ?? [];
        $env = $this->config['env'] ?? [];

        $commandLine = $command;
        foreach ($args as $arg) {
            $commandLine .= ' '.escapeshellarg((string) $arg);
        }

        /** @var array<string, string|false> $fullEnv */
        $fullEnv = array_merge(is_array(getenv()) ? getenv() : [], $env);

        $this->process = proc_open(
            $commandLine,
            $descriptorSpec,
            $this->pipes,
            null,
            $fullEnv,
        );

        if (! is_resource($this->process)) {
            throw new McpException('Failed to start the MCP server process.');
        }

        stream_set_write_buffer($this->pipes[0], 0);
        stream_set_read_buffer($this->pipes[1], 0);
        stream_set_blocking($this->pipes[2], false);

        $status = proc_get_status($this->process);
        if (! $status['running']) {
            $error = is_resource($this->pipes[2]) ? stream_get_contents($this->pipes[2]) : '';
            throw new McpException('Process failed to start: '.$error);
        }

        $this->waitForReady();
    }

    /** @param  array<string, mixed>  $data */
    public function send(array $data): void
    {
        $this->assertRunning();

        if ($this->usesContentLengthFraming()) {
            $json = json_encode($data, JSON_THROW_ON_ERROR);
            $message = 'Content-Length: '.strlen($json)."\r\n\r\n".$json;
        } else {
            $message = json_encode($data, JSON_THROW_ON_ERROR)."\n";
        }

        $bytesWritten = fwrite($this->pipes[0], $message);
        if ($bytesWritten === false || $bytesWritten < strlen($message)) {
            throw new McpException('Failed to write complete request to MCP server.');
        }

        fflush($this->pipes[0]);
    }

    /** @return array<string, mixed> */
    public function receive(): array
    {
        $this->assertRunning();

        $timeout = (float) ($this->config['timeout'] ?? 30);
        $startTime = microtime(true);

        while (microtime(true) - $startTime < $timeout) {
            $this->assertProcessAlive();

            if ($this->usesContentLengthFraming()) {
                $message = $this->tryExtractContentLengthMessage();
                if ($message !== null) {
                    return json_decode($message, true, 512, JSON_THROW_ON_ERROR);
                }
            } else {
                $line = $this->tryExtractLine();
                if ($line !== null) {
                    $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($decoded) && isset($decoded['jsonrpc'])) {
                        return $decoded;
                    }
                }
            }

            if ($this->readMoreFromStdout() === false) {
                usleep(10_000);
            }
        }

        throw new McpException('Timeout waiting for response from MCP server.');
    }

    public function disconnect(): void
    {
        if (! is_resource($this->process)) {
            return;
        }

        foreach ($this->pipes ?? [] as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        if (function_exists('proc_terminate')) {
            $status = proc_get_status($this->process);
            if ($status['running']) {
                proc_terminate($this->process);
                usleep(500_000);
            }
        }

        proc_close($this->process);
        $this->process = null;
        $this->pipes = null;
        $this->readBuffer = '';
        $this->ready = false;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    protected function usesContentLengthFraming(): bool
    {
        return ($this->config['stdio_framing'] ?? 'newline') === 'content-length';
    }

    protected function waitForReady(): void
    {
        if ($this->ready) {
            return;
        }

        $pattern = (string) ($this->config['startup_pattern'] ?? 'running on stdio');
        $timeout = (float) ($this->config['startup_timeout'] ?? 60);
        $startTime = microtime(true);
        $stderr = '';

        while (microtime(true) - $startTime < $timeout) {
            $this->assertProcessAlive();

            if (is_resource($this->pipes[2] ?? null)) {
                $chunk = fread($this->pipes[2], 4096);
                if (is_string($chunk) && $chunk !== '') {
                    $stderr .= $chunk;
                    if ($pattern === '' || str_contains($stderr, $pattern)) {
                        $this->ready = true;

                        return;
                    }
                }
            }

            usleep(50_000);
        }

        throw new McpException('Timed out waiting for MCP server to become ready.');
    }

    protected function assertRunning(): void
    {
        if (! is_resource($this->process) || ! is_resource($this->pipes[0] ?? null)) {
            throw new McpException('Process is not running.');
        }
    }

    protected function assertProcessAlive(): void
    {
        if (! is_resource($this->process)) {
            throw new McpException('Process is not running.');
        }

        $status = proc_get_status($this->process);
        if (! $status['running']) {
            throw new McpException('MCP server process has terminated unexpectedly.');
        }
    }

    protected function readMoreFromStdout(): bool
    {
        if (! is_resource($this->pipes[1] ?? null)) {
            return false;
        }

        stream_set_blocking($this->pipes[1], false);
        $chunk = fread($this->pipes[1], 8192);

        if ($chunk === false || $chunk === '') {
            return false;
        }

        $this->readBuffer .= $chunk;

        return true;
    }

    protected function tryExtractLine(): ?string
    {
        $newlinePos = strpos($this->readBuffer, "\n");
        if ($newlinePos === false) {
            return null;
        }

        $line = substr($this->readBuffer, 0, $newlinePos);
        $this->readBuffer = substr($this->readBuffer, $newlinePos + 1);

        $line = rtrim($line, "\r");

        return trim($line) === '' ? null : $line;
    }

    protected function tryExtractContentLengthMessage(): ?string
    {
        $headerEnd = strpos($this->readBuffer, "\r\n\r\n");
        if ($headerEnd === false) {
            return null;
        }

        $headerBlock = substr($this->readBuffer, 0, $headerEnd);
        if (! preg_match('/Content-Length:\s*(\d+)/i', $headerBlock, $matches)) {
            throw new McpException('Invalid MCP stdio response: missing Content-Length header.');
        }

        $contentLength = (int) $matches[1];
        $bodyStart = $headerEnd + 4;

        if (strlen($this->readBuffer) < $bodyStart + $contentLength) {
            return null;
        }

        $message = substr($this->readBuffer, $bodyStart, $contentLength);
        $this->readBuffer = substr($this->readBuffer, $bodyStart + $contentLength);

        return $message;
    }
}
