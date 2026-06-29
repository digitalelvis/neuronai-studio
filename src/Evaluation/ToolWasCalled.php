<?php

namespace ElvisLopesDigital\NeuronAIStudio\Evaluation;

use NeuronAI\Evaluation\AssertionResult;
use NeuronAI\Evaluation\Assertions\AbstractAssertion;

class ToolWasCalled extends AbstractAssertion
{
    /**
     * @param  array<int, array{name: string, inputs?: array<string, mixed>, result?: string|null, type?: string}>  $toolEvents
     */
    public function __construct(
        protected string $toolName,
        protected array $toolEvents,
    ) {}

    public function evaluate(mixed $actual): AssertionResult
    {
        foreach ($this->toolEvents as $event) {
            if (($event['name'] ?? '') === $this->toolName) {
                return AssertionResult::pass(1.0);
            }
        }

        $called = collect($this->toolEvents)
            ->pluck('name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $calledList = $called === [] ? 'none' : implode(', ', $called);

        return AssertionResult::fail(
            0.0,
            "Expected tool '{$this->toolName}' to be called. Tools called: {$calledList}",
        );
    }
}
