<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\AgentNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\StructuredOutput\StructuredOutputResolver;
use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use DigitalElvis\NeuronAIStudio\Tests\Fixtures\Output\SampleLeadProfile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Usage;
use NeuronAI\Testing\FakeAIProvider;
use NeuronAI\Testing\RequestRecord;

class AgentNodeExecutorTest extends TestCase
{
    protected function fixtureScanConfig(): void
    {
        $fixturesPath = __DIR__.'/Fixtures';

        config([
            'neuronai-studio.export_path' => $fixturesPath,
            'neuronai-studio.export_namespace' => 'DigitalElvis\\NeuronAIStudio\\Tests\\Fixtures',
            'neuronai-studio.structured_output_scan_paths' => [$fixturesPath.'/Output'],
        ]);
    }

    protected function makeExecutor(FakeAIProvider $fakeProvider): AgentNodeExecutor
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

        return new AgentNodeExecutor(
            $runner,
            new MessageFactory,
            app(StructuredOutputResolver::class),
        );
    }

    public function test_execute_passes_attachments_with_default_prompt_when_message_empty(): void
    {
        Storage::fake('local');
        config(['neuronai-studio.attachments.disk' => 'local']);

        $storageKey = 'neuronai-studio/attachments/test.jpg';
        Storage::disk('local')->put(
            $storageKey,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
        );

        $agent = AgentDefinition::create([
            'name' => 'Vision Agent',
            'slug' => 'vision-agent-node',
            'provider' => 'openai',
            'model' => 'gpt-4o',
            'instructions' => 'You are helpful.',
        ]);

        $fakeProvider = new FakeAIProvider(new AssistantMessage('I see the image.'));
        $executor = $this->makeExecutor($fakeProvider);
        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, [
            'input' => '',
            'attachments' => [
                [
                    'type' => 'image',
                    'storage_key' => $storageKey,
                    'mime_type' => 'image/png',
                    'name' => 'test.jpg',
                ],
            ],
        ]);

        $executor->execute([
            'data' => [
                'agent_id' => $agent->id,
                'message' => '',
                'output_key' => 'agent_response',
            ],
        ], $state, $context);

        $this->assertSame('I see the image.', $state->get('agent_response'));

        $fakeProvider->assertSent(function (RequestRecord $record): bool {
            $message = $record->messages[0] ?? null;

            return $message !== null
                && $message->getImage() !== null
                && str_contains((string) $message->getContent(), MessageFactory::ATTACHMENT_ONLY_PROMPT);
        });
    }

    public function test_execute_structured_with_agent_id_stores_validated_output(): void
    {
        $this->fixtureScanConfig();

        $agent = AgentDefinition::create([
            'name' => 'Lead Agent',
            'slug' => 'lead-agent-node',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Extract lead profiles.',
        ]);

        $fakeProvider = new FakeAIProvider(
            new AssistantMessage('{"email": "bob@example.com", "tier": "silver"}'),
        );
        $executor = $this->makeExecutor($fakeProvider);
        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, [
            'input' => 'Bob is a silver lead at bob@example.com',
        ]);

        $executor->execute([
            'data' => [
                'agent_id' => $agent->id,
                'output_key' => 'lead',
                'structured' => true,
                'output_class' => SampleLeadProfile::class,
            ],
        ], $state, $context);

        $this->assertSame([
            'email' => 'bob@example.com',
            'tier' => 'silver',
        ], $state->get('lead'));

        $fakeProvider->assertMethodCallCount('structured', 1);
        $fakeProvider->assertMethodCallCount('chat', 0);
    }

    public function test_execute_structured_false_preserves_chat_behavior(): void
    {
        $agent = AgentDefinition::create([
            'name' => 'Chat Agent',
            'slug' => 'chat-agent-node',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
        ]);

        $fakeProvider = new FakeAIProvider(new AssistantMessage('chat reply'));
        $executor = $this->makeExecutor($fakeProvider);
        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, [
            'input' => 'Hello',
        ]);

        $executor->execute([
            'data' => [
                'agent_id' => $agent->id,
                'output_key' => 'agent_response',
                'structured' => false,
            ],
        ], $state, $context);

        $this->assertSame('chat reply', $state->get('agent_response'));
        $fakeProvider->assertMethodCallCount('chat', 1);
        $fakeProvider->assertMethodCallCount('structured', 0);
    }

    public function test_execute_links_child_run_to_parent_and_rolls_up_usage(): void
    {
        config([
            'neuronai-studio.usage.pricing.openai.gpt-4o-mini' => [
                'prompt_per_1k' => 0.00015,
                'completion_per_1k' => 0.0006,
            ],
        ]);

        $parent = StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => StudioThread::create(['id' => (string) Str::uuid()])->id,
            'status' => 'running',
        ]);

        $agent = AgentDefinition::create([
            'name' => 'Nested Agent',
            'slug' => 'nested-agent-node',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
        ]);

        $fakeProvider = new FakeAIProvider(
            (new AssistantMessage('nested reply'))->setUsage(new Usage(1000, 500)),
        );
        $executor = $this->makeExecutor($fakeProvider);
        $context = new GraphContext([], []);
        $state = new BuilderWorkflowState($context, null, [
            'input' => 'Hello',
            '__studio_run_id' => $parent->id,
        ]);

        $executor->execute([
            'data' => [
                'agent_id' => $agent->id,
                'output_key' => 'agent_response',
            ],
        ], $state, $context);

        $child = StudioRun::query()->where('parent_run_id', $parent->id)->first();
        $this->assertNotNull($child);
        $this->assertSame($parent->id, $child->parent_run_id);
        $this->assertSame(1500, $child->total_tokens);
        $this->assertSame('0.000450', $child->estimated_cost);

        $parent = $parent->fresh();
        $this->assertGreaterThan(0, $parent->total_tokens);
        $this->assertSame(1500, $parent->total_tokens);
        $this->assertSame('0.000450', $parent->estimated_cost);
    }
}
