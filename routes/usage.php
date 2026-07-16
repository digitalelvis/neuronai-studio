<?php

use DigitalElvis\NeuronAIStudio\Http\Controllers\Integration\UsageExportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Usage Export Routes (host metering API)
|--------------------------------------------------------------------------
|
| Registered when `neuronai-studio.usage.export.enabled` is true — independent
| of stream_adapters.enabled. Null route_prefix / middleware fall back to
| stream_adapters.* so the host can share one auth gate.
|
*/

$prefix = config('neuronai-studio.usage.export.route_prefix')
    ?? config('neuronai-studio.stream_adapters.route_prefix', 'api/neuronai');

$middleware = config('neuronai-studio.usage.export.middleware')
    ?? config('neuronai-studio.stream_adapters.middleware', ['api']);

Route::prefix($prefix)
    ->middleware($middleware)
    ->name('neuronai-studio.usage.')
    ->group(function () {
        Route::get('usage', [UsageExportController::class, 'index'])->name('aggregate');
        Route::get('usage/runs/{run}', [UsageExportController::class, 'showRun'])->name('runs.show');
    });
