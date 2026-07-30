<?php

namespace DigitalElvis\NeuronAIStudio\McpServer;

use DigitalElvis\NeuronAIStudio\Models\McpEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamableHttpHandler
{
    public function __construct(
        protected McpToolCatalog $catalog,
        protected McpToolInvoker $invoker,
    ) {}

    public function handle(Request $request, McpEndpoint $endpoint): JsonResponse|StreamedResponse|Response
    {
        if ($request->isMethod('DELETE')) {
            return $this->terminateSession($request, $endpoint);
        }

        if ($request->isMethod('GET')) {
            return $this->handleGet($request, $endpoint);
        }

        $payload = $request->json()->all();

        if ($payload === []) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => ['code' => -32700, 'message' => 'Parse error'],
            ], 400);
        }

        // Batch requests
        if (array_is_list($payload) && isset($payload[0]) && is_array($payload[0])) {
            $results = [];
            foreach ($payload as $message) {
                if (! is_array($message)) {
                    continue;
                }
                $response = $this->dispatch($message, $endpoint, $request);
                if ($response !== null) {
                    $results[] = $response;
                }
            }

            return $this->jsonRpcResponse($results === [] ? new Response('', 202) : response()->json($results), $request, $endpoint);
        }

        $responseBody = $this->dispatch($payload, $endpoint, $request);

        if ($responseBody === null) {
            return $this->jsonRpcResponse(new Response('', 202), $request, $endpoint);
        }

        return $this->jsonRpcResponse(response()->json($responseBody), $request, $endpoint);
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>|null
     */
    protected function dispatch(array $message, McpEndpoint $endpoint, Request $request): ?array
    {
        $id = $message['id'] ?? null;
        $method = (string) ($message['method'] ?? '');
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];

        // Notifications have no id and return no body
        if ($method !== '' && ! array_key_exists('id', $message)) {
            if ($method === 'notifications/initialized') {
                $this->touchSession($request, $endpoint);
            }

            return null;
        }

        try {
            $result = match ($method) {
                'initialize' => $this->initialize($endpoint, $params, $request),
                'ping' => (object) [],
                'tools/list' => ['tools' => $this->listTools($endpoint)],
                'tools/call' => $this->callTool($endpoint, $params),
                default => throw new McpProtocolException("Method not found: {$method}", -32601),
            };

            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'result' => $result,
            ];
        } catch (McpProtocolException $exception) {
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => $exception->getCode() ?: -32603,
                    'message' => $exception->getMessage(),
                ],
            ];
        } catch (\Throwable $exception) {
            return [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => -32603,
                    'message' => $exception->getMessage(),
                ],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function initialize(McpEndpoint $endpoint, array $params, Request $request): array
    {
        $sessionId = $this->ensureSession($request, $endpoint);

        $protocolVersion = (string) (
            $params['protocolVersion']
            ?? config('neuronai-studio.mcp_endpoints.protocol_version', '2024-11-05')
        );

        return [
            'protocolVersion' => $protocolVersion,
            'capabilities' => [
                'tools' => [
                    'listChanged' => false,
                ],
            ],
            'serverInfo' => [
                'name' => (string) config('neuronai-studio.mcp_endpoints.server_name', 'NeuronAI Studio'),
                'version' => (string) config('neuronai-studio.mcp_endpoints.server_version', '1.0.0'),
            ],
            '_meta' => [
                'endpoint' => $endpoint->slug,
                'sessionId' => $sessionId,
            ],
        ];
    }

    /**
     * @return array<int, array{name: string, description: string, inputSchema: array<string, mixed>}>
     */
    protected function listTools(McpEndpoint $endpoint): array
    {
        return array_map(static function (array $tool): array {
            return [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'inputSchema' => $tool['inputSchema'],
            ];
        }, $this->catalog->toolsFor($endpoint));
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function callTool(McpEndpoint $endpoint, array $params): array
    {
        $name = (string) ($params['name'] ?? '');
        if ($name === '') {
            throw new McpProtocolException('Missing tool name.', -32602);
        }

        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        return $this->invoker->invoke($endpoint, $name, $arguments);
    }

    protected function handleGet(Request $request, McpEndpoint $endpoint): StreamedResponse|JsonResponse|Response
    {
        // Legacy SSE stream: keep connection open with heartbeat comments.
        // Clients that speak Streamable HTTP primarily use POST.
        $sessionId = $this->ensureSession($request, $endpoint);

        return response()->stream(function () use ($endpoint) {
            echo ': mcp-endpoint '.$endpoint->slug."\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();

            // Short-lived SSE open for Inspector compatibility; real work is via POST.
            usleep(100000);
            echo "event: message\ndata: ".json_encode([
                'jsonrpc' => '2.0',
                'method' => 'notifications/message',
                'params' => [
                    'level' => 'info',
                    'data' => 'NeuronAI Studio MCP endpoint ready. Use POST for JSON-RPC.',
                ],
            ], JSON_UNESCAPED_UNICODE)."\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
            'Mcp-Session-Id' => $sessionId,
        ]);
    }

    protected function terminateSession(Request $request, McpEndpoint $endpoint): Response
    {
        $sessionId = $request->header('Mcp-Session-Id');
        if (is_string($sessionId) && $sessionId !== '') {
            Cache::forget($this->sessionCacheKey($endpoint, $sessionId));
        }

        return new Response('', 204);
    }

    protected function ensureSession(Request $request, McpEndpoint $endpoint): string
    {
        $existing = $request->header('Mcp-Session-Id');
        if (is_string($existing) && $existing !== '' && Cache::has($this->sessionCacheKey($endpoint, $existing))) {
            $this->touchSession($request, $endpoint, $existing);

            return $existing;
        }

        $sessionId = (string) Str::uuid();
        $this->touchSession($request, $endpoint, $sessionId);

        return $sessionId;
    }

    protected function touchSession(Request $request, McpEndpoint $endpoint, ?string $sessionId = null): void
    {
        $sessionId ??= $request->header('Mcp-Session-Id');
        if (! is_string($sessionId) || $sessionId === '') {
            return;
        }

        $ttl = (int) config('neuronai-studio.mcp_endpoints.session_ttl_seconds', 3600);
        Cache::put($this->sessionCacheKey($endpoint, $sessionId), [
            'endpoint_id' => $endpoint->id,
            'touched_at' => now()->toIso8601String(),
        ], $ttl);
    }

    protected function sessionCacheKey(McpEndpoint $endpoint, string $sessionId): string
    {
        return "neuronai-studio:mcp-endpoint:{$endpoint->id}:session:{$sessionId}";
    }

    protected function jsonRpcResponse(JsonResponse|Response $response, Request $request, McpEndpoint $endpoint): JsonResponse|Response
    {
        $sessionId = $request->attributes->get('mcp_session_id')
            ?? $request->header('Mcp-Session-Id');

        if (! is_string($sessionId) || $sessionId === '') {
            $sessionId = $this->ensureSession($request, $endpoint);
        } else {
            $this->touchSession($request, $endpoint, $sessionId);
        }

        $response->headers->set('Mcp-Session-Id', $sessionId);

        return $response;
    }
}
