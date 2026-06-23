<?php

namespace ElvisLopesDigital\NeuronAIStudio\Tools;

use ElvisLopesDigital\NeuronAIStudio\Models\ToolDefinition;
use Illuminate\Support\Facades\Http;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;
use NeuronAI\Tools\ToolPropertyInterface;

class WebhookTool extends Tool
{
    /** @param  array<string, mixed>  $config */
    public function __construct(
        string $name,
        string $description,
        protected array $config,
        array $properties = [],
    ) {
        parent::__construct($name, $description, $properties);
        $this->setCallable(fn () => $this->executeWebhook());
    }

    public static function fromDefinition(ToolDefinition $definition): self
    {
        $properties = self::buildProperties($definition->input_schema ?? []);

        return new self(
            \Illuminate\Support\Str::slug($definition->slug, '_'),
            $definition->description,
            $definition->config ?? [],
            $properties,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $schema
     * @return array<int, ToolPropertyInterface>
     */
    public static function buildProperties(array $schema): array
    {
        $properties = [];

        foreach ($schema as $property) {
            if (empty($property['name'])) {
                continue;
            }

            $properties[] = new ToolProperty(
                name: $property['name'],
                type: PropertyType::from($property['type'] ?? 'string'),
                description: $property['description'] ?? '',
                required: (bool) ($property['required'] ?? false),
                nullable: (bool) ($property['nullable'] ?? false),
            );
        }

        return $properties;
    }

    public function __invoke(...$args): string
    {
        return $this->executeWebhook();
    }

    protected function executeWebhook(): string
    {
        $inputs = $this->getInputs();
        $method = strtoupper($this->config['method'] ?? 'POST');
        $url = $this->interpolate($this->config['url'] ?? '', $inputs);
        $headers = $this->resolveHeaders($inputs);
        $timeout = (int) config('neuronai-studio.webhook_timeout', 15);

        $this->assertAllowedHost($url);

        $request = Http::timeout($timeout)->withHeaders($headers);

        $response = match ($method) {
            'GET' => $request->get($url, $inputs),
            'PUT' => $request->put($url, $inputs),
            'PATCH' => $request->patch($url, $inputs),
            'DELETE' => $request->delete($url, $inputs),
            default => $request->post($url, $inputs),
        };

        if ($response->failed()) {
            return 'Error: HTTP '.$response->status().' — '.$response->body();
        }

        $body = $response->body();

        return strlen($body) > 8000 ? substr($body, 0, 8000).'…' : $body;
    }

    /** @param  array<string, mixed>  $inputs */
    protected function interpolate(string $template, array $inputs): string
    {
        return preg_replace_callback('/\{(\w+)\}/', function (array $matches) use ($inputs) {
            return (string) ($inputs[$matches[1]] ?? $matches[0]);
        }, $template) ?? $template;
    }

    /** @param  array<string, mixed>  $inputs */
    protected function resolveHeaders(array $inputs): array
    {
        $headers = [];

        foreach ($this->config['headers'] ?? [] as $key => $value) {
            $headers[$key] = $this->interpolate((string) $value, $inputs);
        }

        return $headers;
    }

    protected function assertAllowedHost(string $url): void
    {
        $allowed = config('neuronai-studio.webhook_allowed_hosts', '*');

        if ($allowed === '*' || $allowed === null) {
            return;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if ($host === null || $host === false) {
            throw new \InvalidArgumentException('Invalid webhook URL.');
        }

        $allowedHosts = array_map('trim', explode(',', (string) $allowed));

        foreach ($allowedHosts as $pattern) {
            if ($pattern === $host || fnmatch($pattern, $host)) {
                return;
            }
        }

        throw new \InvalidArgumentException("Webhook host [{$host}] is not allowed.");
    }
}
