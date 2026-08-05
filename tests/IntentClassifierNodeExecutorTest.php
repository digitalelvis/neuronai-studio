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
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Testing\FakeAIProvider;
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
}
