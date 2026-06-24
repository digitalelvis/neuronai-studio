<?php

use ElvisLopesDigital\NeuronAIStudio\Http\Controllers\WorkflowStreamController;
use ElvisLopesDigital\NeuronAIStudio\Http\Controllers\AgentChatStreamController;
use ElvisLopesDigital\NeuronAIStudio\Http\Controllers\AttachmentController;
use ElvisLopesDigital\NeuronAIStudio\Http\Controllers\WorkflowRunResumeController;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Agents\Edit;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Agents\Index as AgentsIndex;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Agents\Playground;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Dashboard;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\McpServers\Edit as McpServersEdit;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\McpServers\Index as McpServersIndex;
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
            Route::match(['GET', 'POST'], '/{agent}/chat/stream', AgentChatStreamController::class)->name('chat.stream');
        });

        Route::prefix('tools')->name('tools.')->group(function () {
            Route::get('/', ToolsIndex::class)->name('index');
            Route::get('/registry', RegistryShow::class)->name('registry');
            Route::get('/create', ToolsEdit::class)->name('create');
            Route::get('/{tool}/edit', ToolsEdit::class)->name('edit');
            Route::get('/{tool}', ToolsShow::class)->name('show');
        });

        Route::prefix('mcp-servers')->name('mcp-servers.')->group(function () {
            Route::get('/', McpServersIndex::class)->name('index');
            Route::get('/create', McpServersEdit::class)->name('create');
            Route::get('/{server}/edit', McpServersEdit::class)->name('edit');
        });

        Route::prefix('workflows')->name('workflows.')->group(function () {
            Route::get('/', WorkflowsIndex::class)->name('index');
            Route::get('/create', Editor::class)->name('create');
            Route::get('/{workflow}/edit', Editor::class)->name('edit');
            Route::match(['GET', 'POST'], '/{workflow}/run/stream', WorkflowStreamController::class)->name('run.stream');
            Route::post('/runs/{run}/resume/stream', WorkflowRunResumeController::class)->name('runs.resume.stream');
            Route::get('/{workflow}/runs', Runs::class)->name('runs');
            Route::get('/runs/{run}', RunDetail::class)->name('runs.show');
        });

        Route::post('/studio/attachments', AttachmentController::class)->name('attachments.store');
    });
