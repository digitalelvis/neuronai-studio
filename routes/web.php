<?php

use ElvisLopesDigital\NeuronAIStudio\Http\Controllers\AgentChatStreamController;
use ElvisLopesDigital\NeuronAIStudio\Http\Controllers\AgentChatThreadController;
use ElvisLopesDigital\NeuronAIStudio\Http\Controllers\AttachmentController;
use ElvisLopesDigital\NeuronAIStudio\Http\Controllers\WorkflowStreamController;
use ElvisLopesDigital\NeuronAIStudio\Http\Controllers\WorkflowTraceController;
use ElvisLopesDigital\NeuronAIStudio\Http\Controllers\WorkflowTraceResumeController;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Agents\Edit;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Agents\Index as AgentsIndex;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Agents\Playground;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Dashboard;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\McpServers\Edit as McpServersEdit;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\McpServers\Index as McpServersIndex;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Templates\Index as TemplatesIndex;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Tools\Edit as ToolsEdit;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Tools\Index as ToolsIndex;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Tools\RegistryShow;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Tools\Show as ToolsShow;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Workflows\Editor;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Workflows\Index as WorkflowsIndex;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Workflows\TraceDetail;
use ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Workflows\Traces;
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
            Route::get('/{agent}/evals', \ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Agents\Evals\Index::class)->name('evals.index');
            Route::get('/{agent}/evals/create', \ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Agents\Evals\Edit::class)->name('evals.create');
            Route::get('/{agent}/evals/{suite}/edit', \ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Agents\Evals\Edit::class)->name('evals.edit');
            Route::get('/{agent}/evals/{suite}/runs', \ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Agents\Evals\Runs::class)->name('evals.runs');
            Route::get('/eval-runs/{run}', \ElvisLopesDigital\NeuronAIStudio\Http\Livewire\Agents\Evals\RunDetail::class)->name('eval-runs.show');
            Route::get('/{agent}/chat/threads/{thread}', AgentChatThreadController::class)->name('chat.threads.show');
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
            Route::get('/preview', Editor::class)->name('preview');
            Route::get('/{workflow}/edit', Editor::class)->name('edit');
            Route::match(['GET', 'POST'], '/{workflow}/trace/stream', WorkflowStreamController::class)->name('trace.stream');
            Route::post('/traces/{trace}/resume/stream', WorkflowTraceResumeController::class)->name('traces.resume.stream');
            Route::get('/{workflow}/traces', Traces::class)->name('traces');
            Route::get('/{workflow}/traces/list', [WorkflowTraceController::class, 'index'])->name('traces.index');
            Route::get('/traces/{trace}', TraceDetail::class)->name('traces.show');
            Route::get('/traces/{trace}/json', [WorkflowTraceController::class, 'show'])->name('traces.show.json');

            Route::redirect('/{workflow}/runs', '/{workflow}/traces', 301)->name('runs');
            Route::redirect('/runs/{trace}', '/traces/{trace}', 301)->name('runs.show');
            Route::post('/runs/{trace}/resume/stream', WorkflowTraceResumeController::class)->name('runs.resume.stream');
            Route::match(['GET', 'POST'], '/{workflow}/run/stream', WorkflowStreamController::class)->name('run.stream');
        });

        Route::get('/templates', TemplatesIndex::class)->name('templates.index');

        Route::post('/studio/attachments', [AttachmentController::class, 'store'])->name('attachments.store');
        Route::get('/studio/attachments/file', [AttachmentController::class, 'show'])->name('attachments.show');
    });
