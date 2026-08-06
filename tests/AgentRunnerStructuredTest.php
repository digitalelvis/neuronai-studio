<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\StructuredOutputValidationException;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use DigitalElvis\NeuronAIStudio\Tests\Fixtures\Output\SampleLeadProfile;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Testing\FakeAIProvider;

class AgentRunnerStructuredTest extends TestCase
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

    protected function runnerWithProvider(FakeAIProvider $provider): AgentRunner
    {
        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn($provider);

        return new AgentRunner(
            $registry,
            $this->createMock(ToolResolver::class),
            $this->createMock(McpToolResolver::class),
            new ToolEventExtractor,
            new MessageFactory,
        );
    }

    public function test_structured_inline_returns_validated_array(): void
    {
        $this->fixtureScanConfig();

        $provider = new FakeAIProvider(
            new AssistantMessage('{"email": "alice@example.com", "tier": "gold"}'),
        );

        $runner = $this->runnerWithProvider($provider);

        $result = $runner->structuredInline([
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Extract lead profile.',
        ], 'Alice is a gold tier lead at alice@example.com', SampleLeadProfile::class);

        $this->assertSame([
            'email' => 'alice@example.com',
            'tier' => 'gold',
        ], $result->structured);
        $this->assertSame('', $result->content);

        $provider->assertMethodCallCount('structured', 1);
    }

    public function test_structured_inline_without_thread_creates_studio_thread_row(): void
    {
        $this->fixtureScanConfig();

        $provider = new FakeAIProvider(
            new AssistantMessage('{"email": "bob@example.com", "tier": "silver"}'),
        );

        $runner = $this->runnerWithProvider($provider);

        $result = $runner->structuredInline([
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Extract lead profile.',
        ], 'Bob is silver at bob@example.com', SampleLeadProfile::class);

        $this->assertNotNull($result->runId);

        $run = \DigitalElvis\NeuronAIStudio\Models\StudioRun::query()->find($result->runId);
        $this->assertNotNull($run);
        $this->assertNotNull($run->thread_id);
        $this->assertDatabaseHas('neuronai_studio_threads', [
            'id' => $run->thread_id,
        ]);
    }

    public function test_structured_inline_with_parent_run_nests_under_parent(): void
    {
        $this->fixtureScanConfig();

        $thread = \DigitalElvis\NeuronAIStudio\Models\StudioThread::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
        ]);
        $parent = \DigitalElvis\NeuronAIStudio\Models\StudioRun::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'thread_id' => $thread->id,
            'status' => 'running',
        ]);

        $provider = new FakeAIProvider(
            new AssistantMessage('{"email": "carol@example.com", "tier": "gold"}'),
        );

        $runner = $this->runnerWithProvider($provider);

        $result = $runner->structuredInline([
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Extract lead profile.',
        ], 'Carol is gold', SampleLeadProfile::class, parentRun: $parent);

        $child = \DigitalElvis\NeuronAIStudio\Models\StudioRun::query()->find($result->runId);
        $this->assertNotNull($child);
        $this->assertSame($parent->id, $child->parent_run_id);
        $this->assertDatabaseHas('neuronai_studio_threads', [
            'id' => $child->thread_id,
        ]);
    }

    public function test_structured_inline_throws_validation_exception_on_invalid_response(): void
    {
        $this->fixtureScanConfig();

        $provider = new FakeAIProvider(
            new AssistantMessage('not valid json'),
            new AssistantMessage('still not valid json'),
        );

        $runner = $this->runnerWithProvider($provider);

        try {
            $runner->structuredInline([
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'instructions' => 'Extract lead profile.',
            ], 'Hello', SampleLeadProfile::class);

            $this->fail('Expected StructuredOutputValidationException was not thrown.');
        } catch (StructuredOutputValidationException $exception) {
            $this->assertNotEmpty($exception->validationErrors);
        }
    }
}
