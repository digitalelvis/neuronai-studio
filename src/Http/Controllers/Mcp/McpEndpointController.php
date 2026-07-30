<?php

namespace DigitalElvis\NeuronAIStudio\Http\Controllers\Mcp;

use DigitalElvis\NeuronAIStudio\McpServer\StreamableHttpHandler;
use DigitalElvis\NeuronAIStudio\Models\McpEndpoint;
use Illuminate\Http\Request;

class McpEndpointController
{
    public function __invoke(Request $request, StreamableHttpHandler $handler)
    {
        /** @var McpEndpoint $endpoint */
        $endpoint = $request->attributes->get('mcp_endpoint')
            ?? $request->route('endpoint');

        return $handler->handle($request, $endpoint);
    }
}
