<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\IntentClassifierNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use Illuminate\Support\Facades\Storage;
use DigitalElvis\NeuronAIStudio\Models\StudioChatMessage;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use DigitalElvis\NeuronAIStudio\Models\StudioTrace;
use DigitalElvis\NeuronAIStudio\Models\StudioTraceSpan;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Classifier\ClassifierInterface;
use NeuronAI\Testing\FakeAIProvider;
use NeuronAI\Testing\FakeClassifier;
use NeuronAI\Testing\RequestRecord;

class IntentClassifierNodeExecutorTest extends TestCase
{
    protected function makeExecutor(FakeAIProvider $fakeProvider): IntentClassifierNodeExecutor
    {
        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn($fakeProvider);

        $runner = new AgentRunner(
            $registry,
            $this->createMock(ToolResolver::class),
            $this->createMock(McpToolResolver::class),
            new ToolEventExtractor,
            new MessageFactory,
        );

        return new IntentClassifierNodeExecutor($runner, new MessageFactory);
    }

    /** @return array<int, array{id: string, name: string, description: string}> */
    protected function sampleIntents(): array
    {
        return [
            ['id' => 'after_sales', 'name' => 'After sales', 'description' => 'After sales questions'],
            ['id' => 'how_to', 'name' => 'How to use', 'description' => 'Product usage questions'],
            ['id' => 'other', 'name' => 'Other', 'description' => 'Other questions'],
        ];
    }

    public function test_execute_routes_to_classified_intent_handle(): void
    {
        $fakeProvider = new FakeAIProvider(
            new AssistantMessage('{"intent_id": "how_to"}'),
        );
        $executor = $this->makeExecutor($fakeProvider);
        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, ['input' => 'How do I reset my password?']);

        $handle = $executor->execute([
            'data' => [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'message' => '{{input}}',
                'output_key' => 'intent',
                'intents' => $this->sampleIntents(),
            ],
        ], $state, $context);

        $this->assertSame('how_to', $handle);
        $this->assertSame('how_to', $state->get('intent'));
        $this->assertSame('How to use', $state->get('intent_name'));
        $fakeProvider->assertMethodCallCount('structured', 1);
    }

    public function test_execute_falls_back_to_other_on_unknown_intent(): void
    {
        $fakeProvider = new FakeAIProvider(
            new AssistantMessage('{"intent_id": "not_a_real_intent"}'),
        );
        $executor = $this->makeExecutor($fakeProvider);
        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, ['input' => 'Hello']);

        $handle = $executor->execute([
            'data' => [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'intents' => $this->sampleIntents(),
            ],
        ], $state, $context);

        $this->assertSame('other', $handle);
        $this->assertSame('other', $state->get('intent'));
    }

    public function test_jev_writes_winner_probability_and_distribution(): void
    {
        $fake = new FakeClassifier([
            ['intent' => ['after_sales' => 0.1, 'how_to' => 0.8, 'other' => 0.1]],
        ]);
        $this->app->instance(ClassifierInterface::class, $fake);

        $executor = $this->makeExecutor(new FakeAIProvider(new AssistantMessage('unused')));
        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, ['input' => 'How do I reset my password?']);

        $handle = $executor->execute([
            'data' => [
                'engine' => 'jev',
                'message' => '{{input}}',
                'output_key' => 'intent',
                'intents' => $this->sampleIntents(),
            ],
        ], $state, $context);

        $this->assertSame('how_to', $handle);
        $this->assertSame('how_to', $state->get('intent'));
        $this->assertSame('How to use', $state->get('intent_name'));
        $this->assertEqualsWithDelta(0.8, $state->get('intent_probability'), 0.0001);
        $this->assertSame(0.8, $state->get('intent_distribution')['how_to']);
        $this->assertSame(0, $state->get('__step_usage')['total_tokens']);
        $fake->assertCallCount(1);
    }

    public function test_jev_threshold_routes_to_other(): void
    {
        $this->app->instance(ClassifierInterface::class, new FakeClassifier([
            ['intent' => ['after_sales' => 0.35, 'how_to' => 0.4, 'other' => 0.25]],
        ]));

        $executor = $this->makeExecutor(new FakeAIProvider(new AssistantMessage('unused')));
        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, ['input' => 'maybe']);

        $handle = $executor->execute([
            'data' => [
                'engine' => 'jev',
                'min_probability' => 0.5,
                'intents' => $this->sampleIntents(),
            ],
        ], $state, $context);

        $this->assertSame('other', $handle);
        $this->assertSame('other', $state->get('intent'));
    }

    public function test_jev_memory_sends_thread_history_in_the_input(): void
    {
        StudioChatMessage::create([
            'thread_id' => 'thread-jev',
            'role' => 'user',
            'content' => ['text' => 'earlier question'],
        ]);

        $fake = new FakeClassifier([
            ['intent' => ['after_sales' => 0.05, 'how_to' => 0.9, 'other' => 0.05]],
        ]);
        $this->app->instance(ClassifierInterface::class, $fake);

        $executor = $this->makeExecutor(new FakeAIProvider(new AssistantMessage('unused')));
        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, [
            'input' => 'and now?',
            '__studio_thread_id' => 'thread-jev',
        ]);

        $executor->execute([
            'data' => [
                'engine' => 'jev',
                'memory' => true,
                'intents' => $this->sampleIntents(),
            ],
        ], $state, $context);

        $fake->assertSent(function ($request): bool {
            $input = $request->input;

            return is_array($input)
                && $input['message'] === 'and now?'
                && ($input['history'][0]['role'] ?? null) === 'user';
        });
    }

    public function test_jev_records_a_classifier_span_with_zero_cost(): void
    {
        $thread = StudioThread::create([]);
        $run = StudioRun::create([
            'thread_id' => $thread->id,
            'status' => 'running',
        ]);
        $trace = StudioTrace::create(['run_id' => $run->id]);

        $this->app->instance(ClassifierInterface::class, new FakeClassifier([
            ['intent' => ['after_sales' => 0.1, 'how_to' => 0.8, 'other' => 0.1]],
        ]));

        $executor = $this->makeExecutor(new FakeAIProvider(new AssistantMessage('unused')));
        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, [
            'input' => 'How do I reset my password?',
            '__studio_trace_id' => $trace->id,
        ]);

        $executor->execute([
            'data' => [
                'engine' => 'jev',
                'intents' => $this->sampleIntents(),
            ],
        ], $state, $context);

        $span = StudioTraceSpan::query()->where('trace_id', $trace->id)->where('type', 'classifier')->first();
        $this->assertNotNull($span);
        $this->assertSame('intent_classification', $span->name);
        $this->assertSame('typesafe', $span->provider);
        $this->assertSame(0, (int) $span->estimated_cost);
        $this->assertSame(0, (int) $span->total_tokens);
    }

    public function test_vision_false_skips_attachments(): void
    {
        Storage::fake('local');
        config(['neuronai-studio.attachments.disk' => 'local']);

        $storageKey = 'neuronai-studio/attachments/test.png';
        Storage::disk('local')->put($storageKey, 'fake-image-bytes');

        $fakeProvider = new FakeAIProvider(
            new AssistantMessage('{"intent_id": "other"}'),
        );
        $executor = $this->makeExecutor($fakeProvider);
        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, [
            'input' => 'What is this?',
            'attachments' => [
                [
                    'type' => 'image',
                    'storage_key' => $storageKey,
                    'mime_type' => 'image/png',
                    'name' => 'test.png',
                ],
            ],
        ]);

        $executor->execute([
            'data' => [
                'provider' => 'openai',
                'model' => 'gpt-4o',
                'vision' => false,
                'intents' => $this->sampleIntents(),
            ],
        ], $state, $context);

        $fakeProvider->assertSent(function (RequestRecord $record): bool {
            $message = $record->messages[0] ?? null;

            return $message !== null && $message->getImage() === null;
        });
    }

    public function test_vision_true_includes_attachments(): void
    {
        Storage::fake('local');
        config(['neuronai-studio.attachments.disk' => 'local']);

        $storageKey = 'neuronai-studio/attachments/test.png';
        Storage::disk('local')->put(
            $storageKey,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
        );

        $fakeProvider = new FakeAIProvider(
            new AssistantMessage('{"intent_id": "other"}'),
        );
        $executor = $this->makeExecutor($fakeProvider);
        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, [
            'input' => 'What is this?',
            'attachments' => [
                [
                    'type' => 'image',
                    'storage_key' => $storageKey,
                    'mime_type' => 'image/png',
                    'name' => 'test.png',
                ],
            ],
        ]);

        $executor->execute([
            'data' => [
                'provider' => 'openai',
                'model' => 'gpt-4o',
                'vision' => true,
                'intents' => $this->sampleIntents(),
            ],
        ], $state, $context);

        $fakeProvider->assertSent(function (RequestRecord $record): bool {
            $message = $record->messages[0] ?? null;

            return $message !== null && $message->getImage() !== null;
        });
    }

    public function test_normalize_and_resolve_helpers(): void
    {
        $intents = IntentClassifierNodeExecutor::normalizeIntents([
            ['id' => 'billing', 'name' => 'Billing', 'description' => 'Payment issues'],
            ['id' => '1bad', 'name' => 'Bad'],
            ['id' => 'other', 'name' => 'Other', 'description' => ''],
        ]);

        $this->assertArrayHasKey('billing', $intents);
        $this->assertArrayHasKey('other', $intents);
        $this->assertArrayNotHasKey('1bad', $intents);

        $this->assertSame('billing', IntentClassifierNodeExecutor::resolveIntentId(
            ['intent_id' => 'billing'],
            $intents,
        ));
        $this->assertSame('other', IntentClassifierNodeExecutor::resolveIntentId(
            ['intent_id' => 'nope'],
            $intents,
        ));
    }

    public function test_memory_off_reuses_workflow_thread_with_in_memory_driver(): void
    {
        $thread = \DigitalElvis\NeuronAIStudio\Models\StudioThread::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
        ]);
        $parent = \DigitalElvis\NeuronAIStudio\Models\StudioRun::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'thread_id' => $thread->id,
            'status' => 'running',
        ]);

        $fakeProvider = new FakeAIProvider(
            new AssistantMessage('{"intent_id": "other"}'),
        );
        $executor = $this->makeExecutor($fakeProvider);
        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, [
            'input' => 'Hello',
            '__studio_thread_id' => $thread->id,
            '__studio_run_id' => $parent->id,
        ]);

        $threadsBefore = \DigitalElvis\NeuronAIStudio\Models\StudioThread::query()->count();

        $executor->execute([
            'data' => [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'memory' => false,
                'intents' => $this->sampleIntents(),
            ],
        ], $state, $context);

        $this->assertSame($threadsBefore, \DigitalElvis\NeuronAIStudio\Models\StudioThread::query()->count());

        $child = \DigitalElvis\NeuronAIStudio\Models\StudioRun::query()
            ->where('parent_run_id', $parent->id)
            ->latest('created_at')
            ->first();

        $this->assertNotNull($child);
        $this->assertSame($thread->id, $child->thread_id);
        $this->assertSame(
            ['driver' => 'in_memory'],
            IntentClassifierNodeExecutor::resolveClassifierMemoryConfig(false, []),
        );
        $this->assertSame(
            'eloquent',
            IntentClassifierNodeExecutor::resolveClassifierMemoryConfig(true, [])['driver'],
        );
    }
}
