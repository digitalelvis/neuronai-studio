<?php

use ElvisLopesDigital\NeuronAIStudio\Http\Controllers\WorkflowStreamController;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Agents\Edit;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Agents\Index as AgentsIndex;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Agents\Playground;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Dashboard;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Tools\Edit as ToolsEdit;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Tools\Index as ToolsIndex;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Tools\RegistryShow;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Tools\Show as ToolsShow;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Workflows\Editor;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Workflows\Index as WorkflowsIndex;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Workflows\RunDetail;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Workflows\Runs;
use Illuminate\Support\Facades\Route;

Route::prefix(config('neuronai-studio.route_prefix', 'neuronai-studio'))
    ->middleware(config('neuronai-studio.middleware', ['web', 'neuronai-studio.auth']))
    ->name('neuronai-studio.')
    ->group(function () {
        Route::get('/', Dashboard::class)->name('dashboard');

        Route::prefix('agents')->name('agents.')->group(function () {
            Route::get('/', AgentsIndex::class)->name('index');
            Route::get('/create', Edit::class)->name('create');
            Route::get('/{agent}/edit', Edit::class)->name('edit');
            Route::get('/{agent}/playground', Playground::class)->name('playground');
        });

        Route::prefix('tools')->name('tools.')->group(function () {
            Route::get('/', ToolsIndex::class)->name('index');
            Route::get('/registry', RegistryShow::class)->name('registry');
            Route::get('/create', ToolsEdit::class)->name('create');
            Route::get('/{tool}/edit', ToolsEdit::class)->name('edit');
            Route::get('/{tool}', ToolsShow::class)->name('show');
        });

        Route::prefix('workflows')->name('workflows.')->group(function () {
            Route::get('/', WorkflowsIndex::class)->name('index');
            Route::get('/create', Editor::class)->name('create');
            Route::get('/{workflow}/edit', Editor::class)->name('edit');
            Route::get('/{workflow}/run/stream', WorkflowStreamController::class)->name('run.stream');
            Route::get('/{workflow}/runs', Runs::class)->name('runs');
            Route::get('/runs/{run}', RunDetail::class)->name('runs.show');
        });
    });
