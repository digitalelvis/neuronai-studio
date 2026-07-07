<?php

use DigitalElvis\NeuronAIStudio\Http\Controllers\Integration\AgentIntegrateStreamController;
use DigitalElvis\NeuronAIStudio\Http\Controllers\Integration\WorkflowIntegrateResumeController;
use DigitalElvis\NeuronAIStudio\Http\Controllers\Integration\WorkflowIntegrateStreamController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| External Integration Routes (Stream Adapters)
|--------------------------------------------------------------------------
|
| Registered only when `neuronai-studio.stream_adapters.enabled` is true.
| Prefix and middleware are fully controlled by the host app via config and
| are independent of the internal Studio UI routes/middleware.
|
*/

Route::prefix(config('neuronai-studio.stream_adapters.route_prefix', 'api/neuronai'))
    ->middleware(config('neuronai-studio.stream_adapters.middleware', ['api']))
    ->name('neuronai-studio.integrate.')
    ->group(function () {
        Route::post('agents/{agent}/stream/{protocol}', AgentIntegrateStreamController::class)
            ->name('agents.stream');

        Route::post('workflows/{workflow}/stream/{protocol}', WorkflowIntegrateStreamController::class)
            ->name('workflows.stream');

        Route::post('workflows/threads/{thread}/runs/{run}/resume/{protocol}', WorkflowIntegrateResumeController::class);

        Route::post('workflows/traces/{trace}/resume/{protocol}', WorkflowIntegrateResumeController::class)
            ->name('workflows.resume');
    });
