<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors\MediaNodeExecutor;
use Illuminate\Support\Facades\Storage;
use NeuronAI\Chat\Enums\SourceType;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\ContentBlocks\ImageContent;
use NeuronAI\Testing\FakeAIProvider;
use NeuronAI\Workflow\WorkflowState;

class MediaNodeExecutorTest extends TestCase
{
    public function test_image_node_stores_an_attachment(): void
    {
        Storage::fake('local');

        $fake = new FakeAIProvider(new AssistantMessage(
            new ImageContent(base64_encode('png'), SourceType::BASE64, 'image/png')
        ));

        $registry = $this->createMock(ProviderRegistry::class);
        $registry->expects($this->once())
            ->method('resolveMedia')
            ->with('openai-image', 'gpt-image-2', ['output_format' => 'png'], null)
            ->willReturn($fake);

        $state = new WorkflowState(['input' => 'a red circle']);
        $handle = (new MediaNodeExecutor($registry))->execute([
            'type' => 'image',
            'data' => [
                'provider' => 'openai',
                'model' => 'gpt-image-2',
                'prompt' => '{{input}}',
                'output_key' => 'image_result',
            ],
        ], $state, new GraphContext([], []));

        $this->assertSame('default', $handle);
        $attachment = $state->get('image_result');
        $this->assertSame('image', $attachment['type']);
        $this->assertSame('image/png', $attachment['mime_type']);
        Storage::disk('local')->assertExists($attachment['storage_key']);
        $this->assertCount(1, $state->get('attachments'));
    }

    public function test_transcribe_requires_audio(): void
    {
        $registry = $this->createMock(ProviderRegistry::class);
        $registry->expects($this->never())->method('resolveMedia');

        $this->expectException(\InvalidArgumentException::class);

        (new MediaNodeExecutor($registry))->execute([
            'type' => 'transcribe',
            'data' => [
                'provider' => 'openai',
                'model' => 'gpt-4o-transcribe',
            ],
        ], new WorkflowState([]), new GraphContext([], []));
    }
}
