<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Observability;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use DigitalElvis\NeuronAIStudio\Models\StudioTrace;
use DigitalElvis\NeuronAIStudio\Models\StudioTraceSpan;
use DigitalElvis\NeuronAIStudio\Observability\LangfuseNeuronObserverAdapter;
use DigitalElvis\NeuronAIStudio\Observability\ObservabilityManager;
use DigitalElvis\NeuronAIStudio\Runtime\TelemetryTracker;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use Inspector\Neuron\InspectorObserver;
use NeuronAI\Observability\ObserverInterface;

class ObservabilityManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        LangfuseNeuronObserverAdapter::resetWarnings();
        unset($_ENV['INSPECTOR_INGESTION_KEY']);
        putenv('INSPECTOR_INGESTION_KEY');
    }

    public function test_resolves_native_tracker_when_native_tracing_enabled(): void
    {
        config([
            'neuronai-studio.observability.native_tracing' => true,
            'neuronai-studio.observability.inspector.enabled' => false,
            'neuronai-studio.observability.langfuse.enabled' => false,
        ]);

        [$run, $trace] = $this->makeRunAndTrace();

        $observers = app(ObservabilityManager::class)->resolveObservers([
            'run' => $run,
            'trace' => $trace,
            'track_nodes' => true,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
        ]);

        $this->assertCount(1, $observers);
        $this->assertInstanceOf(TelemetryTracker::class, $observers[0]);
    }

    public function test_skips_native_tracker_when_native_tracing_disabled(): void
    {
        config([
            'neuronai-studio.observability.native_tracing' => false,
            'neuronai-studio.observability.inspector.enabled' => false,
            'neuronai-studio.observability.langfuse.enabled' => false,
        ]);

        [$run, $trace] = $this->makeRunAndTrace();

        $observers = app(ObservabilityManager::class)->resolveObservers([
            'run' => $run,
            'trace' => $trace,
        ]);

        $this->assertSame([], $observers);
    }

    public function test_attaches_inspector_when_enabled_and_key_present(): void
    {
        $_ENV['INSPECTOR_INGESTION_KEY'] = 'test-ingestion-key';
        putenv('INSPECTOR_INGESTION_KEY=test-ingestion-key');

        config([
            'neuronai-studio.observability.native_tracing' => false,
            'neuronai-studio.observability.inspector.enabled' => true,
            'neuronai-studio.observability.langfuse.enabled' => false,
        ]);

        $observers = app(ObservabilityManager::class)->resolveObservers();

        $this->assertCount(1, $observers);
        $this->assertInstanceOf(InspectorObserver::class, $observers[0]);
    }

    public function test_skips_inspector_when_key_missing(): void
    {
        config([
            'neuronai-studio.observability.native_tracing' => false,
            'neuronai-studio.observability.inspector.enabled' => true,
            'neuronai-studio.observability.langfuse.enabled' => false,
        ]);

        $this->assertFalse(app(ObservabilityManager::class)->isInspectorActive());
        $this->assertSame([], app(ObservabilityManager::class)->resolveObservers());
    }

    public function test_skips_inspector_when_disabled_even_with_key(): void
    {
        $_ENV['INSPECTOR_INGESTION_KEY'] = 'test-ingestion-key';
        putenv('INSPECTOR_INGESTION_KEY=test-ingestion-key');

        config([
            'neuronai-studio.observability.native_tracing' => false,
            'neuronai-studio.observability.inspector.enabled' => false,
            'neuronai-studio.observability.langfuse.enabled' => false,
        ]);

        $this->assertFalse(app(ObservabilityManager::class)->isInspectorActive());
    }

    public function test_langfuse_active_requires_keys(): void
    {
        config([
            'neuronai-studio.observability.langfuse.enabled' => true,
            'neuronai-studio.observability.langfuse.public_key' => null,
            'neuronai-studio.observability.langfuse.secret_key' => null,
        ]);

        $this->assertFalse(app(ObservabilityManager::class)->isLangfuseActive());

        config([
            'neuronai-studio.observability.langfuse.public_key' => 'pk-lf-test',
            'neuronai-studio.observability.langfuse.secret_key' => 'sk-lf-test',
        ]);

        $this->assertTrue(app(ObservabilityManager::class)->isLangfuseActive());
    }

    public function test_langfuse_without_package_is_noop_on_resolve(): void
    {
        config([
            'neuronai-studio.observability.native_tracing' => false,
            'neuronai-studio.observability.inspector.enabled' => false,
            'neuronai-studio.observability.langfuse.enabled' => true,
            'neuronai-studio.observability.langfuse.public_key' => 'pk-lf-test',
            'neuronai-studio.observability.langfuse.secret_key' => 'sk-lf-test',
        ]);

        $observers = app(ObservabilityManager::class)->resolveObservers();

        $this->assertSame([], $observers);
    }

    public function test_attach_registers_all_active_observers(): void
    {
        $_ENV['INSPECTOR_INGESTION_KEY'] = 'test-ingestion-key';
        putenv('INSPECTOR_INGESTION_KEY=test-ingestion-key');

        config([
            'neuronai-studio.observability.native_tracing' => true,
            'neuronai-studio.observability.inspector.enabled' => true,
            'neuronai-studio.observability.langfuse.enabled' => false,
        ]);

        [$run, $trace] = $this->makeRunAndTrace();
        $target = new CollectingObservee;

        app(ObservabilityManager::class)->attach($target, [
            'run' => $run,
            'trace' => $trace,
            'track_nodes' => true,
        ]);

        $this->assertCount(2, $target->observers);
        $this->assertInstanceOf(TelemetryTracker::class, $target->observers[0]);
        $this->assertInstanceOf(InspectorObserver::class, $target->observers[1]);
    }

    public function test_native_off_does_not_create_spans_via_tracker(): void
    {
        config([
            'neuronai-studio.observability.native_tracing' => false,
            'neuronai-studio.observability.inspector.enabled' => false,
            'neuronai-studio.observability.langfuse.enabled' => false,
        ]);

        [$run, $trace] = $this->makeRunAndTrace();
        $target = new CollectingObservee;

        app(ObservabilityManager::class)->attach($target, [
            'run' => $run,
            'trace' => $trace,
        ]);

        $this->assertSame([], $target->observers);
        $this->assertSame(0, StudioTraceSpan::query()->count());
    }

    public function test_record_direct_llm_generation_does_not_throw_without_package(): void
    {
        config([
            'neuronai-studio.observability.langfuse.enabled' => true,
            'neuronai-studio.observability.langfuse.public_key' => 'pk-lf-test',
            'neuronai-studio.observability.langfuse.secret_key' => 'sk-lf-test',
        ]);

        app(ObservabilityManager::class)->recordDirectLlmGeneration([
            'name' => 'llm-node',
            'model' => 'gpt-4o-mini',
            'provider' => 'openai',
            'output' => 'hello',
            'prompt_tokens' => 1,
            'completion_tokens' => 2,
        ]);

        $this->assertTrue(true);
    }

    public function test_langfuse_adapter_forwards_events_with_branch_id(): void
    {
        $inner = new class implements ObserverInterface
        {
            public array $calls = [];

            public function onEvent(string $event, object $source, mixed $data = null, ?string $branchId = null): void
            {
                $this->calls[] = [$event, $source, $data, $branchId];
            }
        };

        $adapter = new LangfuseNeuronObserverAdapter($inner);
        $source = new \stdClass;
        $adapter->onEvent('inference-stop', $source, ['x' => 1], 'branch-a');

        $this->assertSame([['inference-stop', $source, ['x' => 1], 'branch-a']], $inner->calls);
    }

    public function test_langfuse_adapter_drops_branch_id_for_legacy_inner(): void
    {
        $inner = new class
        {
            public array $calls = [];

            public function onEvent(string $event, object $source, mixed $data = null): void
            {
                $this->calls[] = [$event, $source, $data];
            }
        };

        $adapter = new LangfuseNeuronObserverAdapter($inner);
        $source = new \stdClass;
        $adapter->onEvent('inference-stop', $source, ['x' => 1], 'branch-a');

        $this->assertSame([['inference-stop', $source, ['x' => 1]]], $inner->calls);
    }

    /**
     * @return array{0: StudioRun, 1: StudioTrace}
     */
    protected function makeRunAndTrace(): array
    {
        $agent = AgentDefinition::query()->create([
            'name' => 'Obs Agent',
            'slug' => 'obs-agent-'.uniqid(),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'test',
        ]);

        $thread = StudioThread::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'entity_type' => AgentDefinition::class,
            'entity_id' => $agent->id,
        ]);

        $run = StudioRun::query()->create([
            'thread_id' => $thread->id,
            'status' => 'running',
            'input' => ['message' => 'hi'],
        ]);

        $trace = StudioTrace::query()->create([
            'run_id' => $run->id,
        ]);

        return [$run, $trace];
    }
}

class CollectingObservee
{
    /** @var list<ObserverInterface> */
    public array $observers = [];

    public function observe(ObserverInterface $observer): self
    {
        $this->observers[] = $observer;

        return $this;
    }
}
