<?php

use DigitalElvis\NeuronAIStudio\Http\Controllers\Mcp\McpEndpointController;
use DigitalElvis\NeuronAIStudio\Http\Middleware\AuthenticateMcpEndpoint;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Outbound MCP Endpoints (Studio → MCP server)
|--------------------------------------------------------------------------
|
| Registered only when `neuronai-studio.mcp_endpoints.enabled` is true.
| Prefix and middleware are host-controlled. API key auth is applied per endpoint.
|
*/

Route::prefix(config('neuronai-studio.mcp_endpoints.route_prefix', 'api/neuronai/mcp'))
    ->middleware(array_values(array_filter(array_merge(
        config('neuronai-studio.mcp_endpoints.middleware', ['api']),
        [AuthenticateMcpEndpoint::class],
    ))))
    ->name('neuronai-studio.mcp-endpoints.http.')
    ->group(function () {
        Route::match(['GET', 'POST', 'DELETE'], '{slug}', McpEndpointController::class)
            ->name('handle')
            ->where('slug', '[A-Za-z0-9\-_]+');
    });
