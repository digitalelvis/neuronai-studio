<?php

namespace DigitalElvis\NeuronAIStudio\Http\Middleware;

use DigitalElvis\NeuronAIStudio\Models\McpEndpoint;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMcpEndpoint
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = (string) $request->route('slug');
        $endpoint = McpEndpoint::findBySlug($slug);

        if (! $endpoint) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => ['code' => -32001, 'message' => 'MCP endpoint not found.'],
            ], 404);
        }

        if (! $endpoint->enabled) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => ['code' => -32001, 'message' => 'MCP endpoint is disabled.'],
            ], 403);
        }

        if (! $endpoint->hasApiKey()) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => ['code' => -32001, 'message' => 'MCP endpoint has no API key configured.'],
            ], 403);
        }

        $plain = $this->extractApiKey($request);

        if ($plain === null || ! $endpoint->verifyApiKey($plain)) {
            return response()->json([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => ['code' => -32001, 'message' => 'Unauthorized.'],
            ], 401);
        }

        $request->attributes->set('mcp_endpoint', $endpoint);
        $request->route()?->setParameter('endpoint', $endpoint);

        return $next($request);
    }

    protected function extractApiKey(Request $request): ?string
    {
        $header = $request->header('x-api-key');
        if (is_string($header) && $header !== '') {
            return $header;
        }

        $authorization = $request->header('Authorization');
        if (is_string($authorization) && preg_match('/^\s*Bearer\s+(.+)$/i', $authorization, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }
}
