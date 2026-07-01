<?php

namespace DigitalElvis\NeuronAIStudio;

use DigitalElvis\NeuronAIStudio\Commands\EvalSuiteCommand;
use DigitalElvis\NeuronAIStudio\Commands\EvaluationsCommand;
use DigitalElvis\NeuronAIStudio\Commands\ExportCommand;
use DigitalElvis\NeuronAIStudio\Commands\InstallCommand;
use DigitalElvis\NeuronAIStudio\Commands\MakeToolCommand;
use DigitalElvis\NeuronAIStudio\Http\Middleware\EnsureNeuronAIStudioAuthorized;
use DigitalElvis\NeuronAIStudio\Registry\McpRegistry;
use DigitalElvis\NeuronAIStudio\Registry\NodeTypeRegistry;
use DigitalElvis\NeuronAIStudio\Registry\OutputClassRegistry;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Registry\ToolRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\AgentNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\ConditionNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\DelayNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\HumanNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\LlmNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\NodeExecutorRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\McpNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\RagNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\SetStateNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\StartNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\StopNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\ToolNodeExecutor;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class NeuronAIStudioServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/neuronai-studio.php', 'neuronai-studio');

        $this->app->singleton(NodeTypeRegistry::class, function () {
            return new NodeTypeRegistry;
        });

        $this->app->singleton(ProviderRegistry::class, function () {
            return new ProviderRegistry;
        });

        $this->app->singleton(ToolRegistry::class, function () {
            return new ToolRegistry;
        });

        $this->app->singleton(OutputClassRegistry::class, function () {
            return new OutputClassRegistry;
        });

        $this->app->singleton(McpRegistry::class, function () {
            return new McpRegistry;
        });

        $this->app->singleton(McpToolResolver::class, function ($app) {
            return new McpToolResolver($app->make(McpRegistry::class));
        });

        $this->app->singleton(NodeExecutorRegistry::class, function ($app) {
            return new NodeExecutorRegistry;
        });

        $this->app->singleton(Registry\TemplateRegistry::class, function () {
            return new Registry\TemplateRegistry;
        });

        $this->app->singleton('neuronai-studio', function ($app) {
            return new NeuronAIStudioManager(
                $app->make(NodeTypeRegistry::class),
                $app->make(ProviderRegistry::class),
            );
        });
    }

    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerRoutes();
        $this->registerMiddleware();
        $this->registerGate();
        $this->registerNodeTypes();
        $this->registerLivewireComponents();
        $this->registerCommands();
        $this->registerViews();
    }

    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/neuronai-studio.php' => config_path('neuronai-studio.php'),
            ], 'neuronai-studio-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'neuronai-studio-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/neuronai-studio'),
            ], 'neuronai-studio-views');

            $this->publishes([
                __DIR__.'/../stubs/evaluation.php.stub' => base_path('evaluation.php'),
            ], 'neuronai-studio-evaluation');

            $this->publishes([
                __DIR__.'/../stubs/evaluator.stub' => app_path('Evaluators/ExampleAgentEvaluator.php'),
            ], 'neuronai-studio-evaluator');

            $this->publishes([
                __DIR__.'/../resources/css' => public_path('vendor/neuronai-studio/css'),
                __DIR__.'/../resources/js/canvas' => public_path('vendor/neuronai-studio/js/canvas'),
                __DIR__.'/../resources/js/dist' => public_path('vendor/neuronai-studio/js/dist'),
            ], 'neuronai-studio-assets');
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }

    protected function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('neuronai-studio.auth', EnsureNeuronAIStudioAuthorized::class);
    }

    protected function registerGate(): void
    {
        $gateName = config('neuronai-studio.gate', 'viewNeuronAIStudio');

        $this->app->afterResolving(Gate::class, function (Gate $gate) use ($gateName) {
            $gate->define($gateName, function ($user = null) {
                if (app()->environment('local')) {
                    return true;
                }

                return $user !== null;
            });
        });
    }

    protected function registerNodeTypes(): void
    {
        $registry = $this->app->make(NodeTypeRegistry::class);
        $executors = $this->app->make(NodeExecutorRegistry::class);

        $types = [
            'start' => StartNodeExecutor::class,
            'stop' => StopNodeExecutor::class,
            'agent' => AgentNodeExecutor::class,
            'llm' => LlmNodeExecutor::class,
            'condition' => ConditionNodeExecutor::class,
            'set_state' => SetStateNodeExecutor::class,
            'tool' => ToolNodeExecutor::class,
            'rag' => RagNodeExecutor::class,
            'delay' => DelayNodeExecutor::class,
            'mcp' => McpNodeExecutor::class,
            'human' => HumanNodeExecutor::class,
        ];

        foreach ($types as $type => $executorClass) {
            $registry->register($type, $executorClass);
            $executors->register($type, $this->app->make($executorClass));
        }
    }

    protected function registerLivewireComponents(): void
    {
        Livewire::component('neuronai-studio.dashboard', Http\Livewire\Dashboard::class);
        Livewire::component('neuronai-studio.agents.index', Http\Livewire\Agents\Index::class);
        Livewire::component('neuronai-studio.agents.edit', Http\Livewire\Agents\Edit::class);
        Livewire::component('neuronai-studio.agents.playground', Http\Livewire\Agents\Playground::class);
        Livewire::component('neuronai-studio.tools.index', Http\Livewire\Tools\Index::class);
        Livewire::component('neuronai-studio.tools.edit', Http\Livewire\Tools\Edit::class);
        Livewire::component('neuronai-studio.tools.show', Http\Livewire\Tools\Show::class);
        Livewire::component('neuronai-studio.tools.registry', Http\Livewire\Tools\RegistryShow::class);
        Livewire::component('neuronai-studio.mcp-servers.index', Http\Livewire\McpServers\Index::class);
        Livewire::component('neuronai-studio.mcp-servers.edit', Http\Livewire\McpServers\Edit::class);
        Livewire::component('neuronai-studio.workflows.index', Http\Livewire\Workflows\Index::class);
        Livewire::component('neuronai-studio.workflows.editor', Http\Livewire\Workflows\Editor::class);
        Livewire::component('neuronai-studio.workflows.traces', Http\Livewire\Workflows\Traces::class);
        Livewire::component('neuronai-studio.workflows.trace-detail', Http\Livewire\Workflows\TraceDetail::class);
        Livewire::component('neuronai-studio.agents.evals.index', Http\Livewire\Agents\Evals\Index::class);
        Livewire::component('neuronai-studio.agents.evals.edit', Http\Livewire\Agents\Evals\Edit::class);
        Livewire::component('neuronai-studio.agents.evals.runs', Http\Livewire\Agents\Evals\Runs::class);
        Livewire::component('neuronai-studio.agents.evals.run-detail', Http\Livewire\Agents\Evals\RunDetail::class);
        Livewire::component('neuronai-studio.templates.index', Http\Livewire\Templates\Index::class);
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                ExportCommand::class,
                MakeToolCommand::class,
                EvaluationsCommand::class,
                EvalSuiteCommand::class,
            ]);
        }
    }

    protected function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'neuronai-studio');

        Blade::directive('neuronAIStudioStyles', function () {
            return "<?php echo '<link rel=\"stylesheet\" href=\"'.asset('vendor/neuronai-studio/css/neuronai-studio.css').'\">'; ?>";
        });
    }
}
