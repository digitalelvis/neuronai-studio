<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Context;

use NeuronAI\Chat\Messages\ToolResultMessage;
use NeuronAI\Tools\ToolInterface;

/**
 * Cap tool result payloads before they re-enter prompt/history.
 */
final class ToolResultBudgeter
{
    public function __construct(
        private readonly TokenBudgetTruncator $truncator = new TokenBudgetTruncator,
    ) {}

    /**
     * @return array{0: ToolResultMessage, 1: list<array<string, mixed>>}
     */
    public function apply(ToolResultMessage $message, int $budgetTokens): array
    {
        $events = [];
        $tools = [];

        foreach ($message->getTools() as $tool) {
            $tools[] = $this->truncateTool($tool, $budgetTokens, $events);
        }

        return [new ToolResultMessage($tools), $events];
    }

    /**
     * @param  list<array<string, mixed>>  $events
     */
    private function truncateTool(ToolInterface $tool, int $budgetTokens, array &$events): ToolInterface
    {
        $result = $tool->getResult();
        $truncation = $this->truncator->truncate($result, $budgetTokens);

        if (! $truncation->truncated) {
            return $tool;
        }

        if (! method_exists($tool, 'setResult')) {
            return $tool;
        }

        $tool->setResult($truncation->text);
        $events[] = [
            'kind' => 'tool_result',
            'tool' => $tool->getName(),
            'tokens_before' => $truncation->tokensBefore,
            'tokens_after' => $truncation->tokensAfter,
            'strategy' => $truncation->strategy,
        ];

        return $tool;
    }
}
