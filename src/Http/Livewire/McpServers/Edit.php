<?php

namespace DigitalElvis\NeuronAIStudio\Http\Livewire\McpServers;

use DigitalElvis\NeuronAIStudio\Models\McpServer;
use DigitalElvis\NeuronAIStudio\Registry\McpRegistry;
use DigitalElvis\NeuronAIStudio\Support\StudioLayout;
use Illuminate\Support\Str;
use Livewire\Component;

class Edit extends Component
{
    public ?McpServer $server = null;

    public string $name = '';

    public string $description = '';

    public string $transport = 'stdio';

    public string $command = '';

    public string $argsJson = '[]';

    public string $url = '';

    public string $tokenEnv = '';

    public string $headersJson = '{}';

    public string $envJson = '{}';

    public string $onlyTools = '';

    public string $excludeToolsJson = '[]';

    public int $timeout = 30;

    public bool $async = false;

    public bool $enabled = true;

    /** @var array<int, string> */
    public array $testTools = [];

    public ?string $testError = null;

    public function mount(?McpServer $server = null): void
    {
        $this->server = $server;

        if ($server?->exists) {
            $this->name = $server->name;
            $this->description = (string) $server->description;
            $this->transport = $server->transport;
            $this->command = (string) $server->command;
            $this->argsJson = json_encode($server->args ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $this->url = (string) $server->url;
            $this->tokenEnv = (string) $server->token_env;
            $this->headersJson = json_encode($server->headers ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $this->envJson = json_encode($server->env ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $this->onlyTools = (string) $server->only_tools;
            $this->excludeToolsJson = json_encode($server->exclude_tools ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $this->timeout = (int) $server->timeout;
            $this->async = (bool) $server->async;
            $this->enabled = (bool) $server->enabled;
        }
    }

    public function updatedTransport(string $value): void
    {
        if ($value === 'sse') {
            $this->async = true;
        }

        if ($value === 'http') {
            $this->async = false;
        }
    }

    public function testConnection(McpRegistry $registry): void
    {
        $this->testTools = [];
        $this->testError = null;

        try {
            $payload = $this->buildPayload(Str::slug($this->name ?: 'preview-server'));

            if ($payload['transport'] === 'stdio' && ! empty($payload['command'])) {
                $registry->assertStdioCommandAllowed($payload['command']);
            }

            $result = $this->server?->exists
                ? $registry->testConnection($this->server->slug)
                : $registry->testConnectionFromPayload($payload);

            if ($result['success'] ?? false) {
                $this->testTools = $result['tools'] ?? [];
                session()->flash('success', 'Connection successful. Found '.count($this->testTools).' tool(s).');

                return;
            }

            $this->testError = $result['error'] ?? 'Connection failed.';
        } catch (\Throwable $exception) {
            $this->testError = $exception->getMessage();
        }
    }

    public function save(McpRegistry $registry): void
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'transport' => 'required|in:stdio,http,sse',
            'command' => 'nullable|string|max:255',
            'argsJson' => 'nullable|string',
            'url' => 'nullable|string|max:2048',
            'tokenEnv' => 'nullable|string|max:255',
            'headersJson' => 'nullable|string',
            'envJson' => 'nullable|string',
            'onlyTools' => 'nullable|string',
            'excludeToolsJson' => 'nullable|string',
            'timeout' => 'required|integer|min:1|max:300',
            'async' => 'boolean',
            'enabled' => 'boolean',
        ]);

        $slug = Str::slug($validated['name']);
        $payload = $this->buildPayload($slug);

        if ($payload['transport'] === 'stdio') {
            if (empty($payload['command'])) {
                $this->addError('command', 'Command is required for stdio transport.');

                return;
            }

            $registry->assertStdioCommandAllowed($payload['command']);
        } else {
            if (empty($payload['url'])) {
                $this->addError('url', 'URL is required for HTTP/SSE transport.');

                return;
            }
        }

        if ($this->server?->exists) {
            $this->server->update($payload);
        } else {
            $this->server = McpServer::create($payload);
        }

        session()->flash('success', 'MCP server saved successfully.');

        $this->redirect(route('neuronai-studio.mcp-servers.index'));
    }

    /** @return array<string, mixed> */
    protected function buildPayload(string $slug): array
    {
        $args = json_decode($this->argsJson ?: '[]', true);
        $headers = json_decode($this->headersJson ?: '{}', true);
        $env = json_decode($this->envJson ?: '{}', true);
        $excludeTools = json_decode($this->excludeToolsJson ?: '[]', true);

        if (! is_array($args)) {
            throw new \InvalidArgumentException('Args must be valid JSON array.');
        }

        if (! is_array($headers)) {
            throw new \InvalidArgumentException('Headers must be valid JSON object.');
        }

        if (! is_array($env)) {
            throw new \InvalidArgumentException('Env must be valid JSON object.');
        }

        if (! is_array($excludeTools)) {
            throw new \InvalidArgumentException('Exclude tools must be valid JSON array.');
        }

        return [
            'name' => $this->name,
            'slug' => $slug,
            'description' => $this->description,
            'transport' => $this->transport,
            'command' => $this->transport === 'stdio' ? $this->command : null,
            'args' => $this->transport === 'stdio' ? $args : null,
            'url' => in_array($this->transport, ['http', 'sse'], true) ? $this->url : null,
            'token_env' => in_array($this->transport, ['http', 'sse'], true) ? ($this->tokenEnv ?: null) : null,
            'headers' => in_array($this->transport, ['http', 'sse'], true) ? $headers : null,
            'env' => $this->transport === 'stdio' ? $env : null,
            'only_tools' => $this->onlyTools ?: null,
            'exclude_tools' => $excludeTools,
            'timeout' => $this->timeout,
            'async' => $this->transport === 'sse' ? true : $this->async,
            'enabled' => $this->enabled,
        ];
    }

    public function render()
    {
        return view('neuronai-studio::livewire.mcp-servers.edit')
            ->layout('neuronai-studio::layouts.app', StudioLayout::params(
                breadcrumbs: [
                    ['label' => 'MCP Servers', 'url' => route('neuronai-studio.mcp-servers.index')],
                    ['label' => $this->server?->exists ? $this->name : 'New Server'],
                ],
                title: $this->server?->exists ? 'Edit MCP Server' : 'Create MCP Server',
            ));
    }
}
