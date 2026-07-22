<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Runtime\Context;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\Context\TokenBudgetTruncator;
use DigitalElvis\NeuronAIStudio\Runtime\Context\ToolResultBudgeter;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\Memory\StudioInMemoryChatHistory;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Chat\Messages\ToolResultMessage;
use NeuronAI\Testing\FakeAIProvider;
use NeuronAI\Tools\Tool;

class ToolResultBudgetTest extends TestCase
{
    public function test_budgeter_truncates_tool_result_with_marker(): void
    {
        $tool = Tool::make('scraper', 'Scrapes')
            ->setCallId('call_1')
            ->setResult(str_repeat('x', 50000));

        [$message, $events] = (new ToolResultBudgeter)->apply(
            new ToolResultMessage([$tool]),
            1000,
        );

        $result = $message->getTools()[0]->getResult();
        $this->assertStringContainsString(TokenBudgetTruncator::MARKER, $result);
        $this->assertLessThanOrEqual(1000, (new TokenBudgetTruncator)->estimateTokens($result));
        $this->assertCount(1, $events);
        $this->assertSame('tool_result', $events[0]['kind']);
        $this->assertSame('scraper', $events[0]['tool']);
    }

    public function test_no_budget_leaves_history_unchanged(): void
    {
        $payload = str_repeat('y', 5000);
        $tool = Tool::make('scraper', 'Scrapes')
            ->setCallId('call_1')
            ->setResult($payload);

        $history = new StudioInMemoryChatHistory(contextWindow: 150000);
        $history->addMessage(new ToolResultMessage([$tool]));

        /** @var ToolResultMessage $stored */
        $stored = $history->getMessages()[0];
        $this->assertSame($payload, $stored->getTools()[0]->getResult());
        $this->assertSame([], $history->pullToolTruncationEvents());
    }

    public function test_history_applies_budget_before_persist_path(): void
    {
        $payload = str_repeat('z', 50000);
        $tool = Tool::make('scraper', 'Scrapes')
            ->setCallId('call_1')
            ->setResult($payload);

        $history = new StudioInMemoryChatHistory(
            contextWindow: 150000,
            toolResultBudget: 1000,
        );
        $history->addMessage(new ToolResultMessage([$tool]));

        /** @var ToolResultMessage $stored */
        $stored = $history->getMessages()[0];
        $result = $stored->getTools()[0]->getResult();
        $this->assertStringContainsString(TokenBudgetTruncator::MARKER, $result);
        $this->assertNotSame($payload, $result);
        $this->assertNotEmpty($history->pullToolTruncationEvents());
    }

    public function test_run_inline_truncates_verbose_tool_and_emits_tool_result_event(): void
    {
        $callable = fn () => str_repeat('A', 50000);
        $verbose = Tool::make('verbose_dump', 'Returns a huge dump')
            ->setCallable($callable);

        $call = Tool::make('verbose_dump', 'Returns a huge dump')
            ->setCallable($callable)
            ->setInputs([])
            ->setCallId('call_verbose');

        $provider = new FakeAIProvider(
            new ToolCallMessage(null, [$call]),
            new AssistantMessage('Summarized the dump.'),
        );

        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn($provider);

        $toolResolver = $this->createMock(ToolResolver::class);
        $toolResolver->method('resolveMany')->willReturn([$verbose]);

        $runner = new AgentRunner(
            $registry,
            $toolResolver,
            $this->createMock(McpToolResolver::class),
            new ToolEventExtractor,
            new MessageFactory,
        );

        $definition = AgentDefinition::create([
            'name' => 'Budget Tool Agent',
            'slug' => 'budget-tool-'.uniqid(),
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Use tools.',
            'tools' => [],
            'memory_config' => ['budget_tool_results' => 1000],
        ]);

        $result = $runner->runInline([
            'provider' => $definition->provider,
            'model' => $definition->model,
            'instructions' => $definition->instructions,
            'tools' => [['ref' => 'tool:verbose']],
            'budget_tool_results' => 1000,
        ], 'Dump please', $definition);

        $this->assertSame('Summarized the dump.', $result->content);
        $this->assertNotEmpty($result->toolEvents);
        $toolResultEvents = array_values(array_filter(
            $result->toolEvents,
            fn (array $e) => ($e['type'] ?? '') === 'result',
        ));
        $this->assertNotEmpty($toolResultEvents);
        $truncated = (string) ($toolResultEvents[0]['result'] ?? '');
        $this->assertStringContainsString(TokenBudgetTruncator::MARKER, $truncated);
    }
}
