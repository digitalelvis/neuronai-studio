<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\AgentNodeExecutor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use Illuminate\Support\Facades\Storage;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Testing\FakeAIProvider;
use NeuronAI\Testing\RequestRecord;

class AgentNodeExecutorTest extends TestCase
{
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
        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn($fakeProvider);

        $runner = new AgentRunner(
            $registry,
            $this->createMock(ToolResolver::class),
            $this->createMock(McpToolResolver::class),
            new ToolEventExtractor,
            new MessageFactory,
        );

        $executor = new AgentNodeExecutor($runner, new MessageFactory);
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
}
