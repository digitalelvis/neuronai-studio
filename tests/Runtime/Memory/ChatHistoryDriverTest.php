<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Runtime\Memory;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\DynamicAgent;
use DigitalElvis\NeuronAIStudio\Runtime\Memory\MemoryConfig;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use NeuronAI\Chat\History\EloquentChatHistory;
use NeuronAI\Chat\History\InMemoryChatHistory;
use ReflectionMethod;
use ReflectionObject;

class ChatHistoryDriverTest extends TestCase
{
    public function test_absent_driver_uses_in_memory_without_thread(): void
    {
        $history = $this->historyFor(null, null);

        $this->assertInstanceOf(InMemoryChatHistory::class, $history);
    }

    public function test_absent_driver_uses_eloquent_with_thread(): void
    {
        $history = $this->historyFor(null, 'thread-1');

        $this->assertInstanceOf(EloquentChatHistory::class, $history);
    }

    public function test_eloquent_driver_uses_eloquent_with_thread(): void
    {
        $history = $this->historyFor([
            'driver' => MemoryConfig::DRIVER_ELOQUENT,
            'context_window' => 1000,
        ], 'thread-1');

        $this->assertInstanceOf(EloquentChatHistory::class, $history);
    }

    public function test_in_memory_driver_forces_in_memory_even_with_thread(): void
    {
        $history = $this->historyFor([
            'driver' => MemoryConfig::DRIVER_IN_MEMORY,
            'context_window' => 1000,
        ], 'thread-1');

        $this->assertInstanceOf(InMemoryChatHistory::class, $history);
    }

    public function test_eloquent_driver_without_thread_falls_back_to_in_memory(): void
    {
        $history = $this->historyFor([
            'driver' => MemoryConfig::DRIVER_ELOQUENT,
        ], null);

        $this->assertInstanceOf(InMemoryChatHistory::class, $history);
    }

    /**
     * @param  array<string, mixed>|null  $memoryConfig
     */
    private function historyFor(?array $memoryConfig, ?string $threadId): object
    {
        $definition = AgentDefinition::create([
            'name' => 'Driver Agent',
            'slug' => 'driver-agent-'.uniqid(),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Test',
            'tools' => [],
            'memory_config' => $memoryConfig,
        ]);

        $runner = app(AgentRunner::class);
        $method = new ReflectionMethod($runner, 'makeAgent');
        $method->setAccessible(true);

        /** @var DynamicAgent $agent */
        $agent = $method->invoke($runner, $definition, [
            'provider' => $definition->provider,
            'model' => $definition->model,
            'instructions' => $definition->instructions,
            'tools' => [],
        ], $threadId);

        $chatHistory = new ReflectionMethod($agent, 'chatHistory');
        $chatHistory->setAccessible(true);

        return $chatHistory->invoke($agent);
    }
}
