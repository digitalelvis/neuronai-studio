<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Runtime\Context;

use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\Context\TokenBudgetTruncator;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\Memory\MemoryConfig;
use DigitalElvis\NeuronAIStudio\Runtime\StateTemplateInterpolator;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;

class StateFieldBudgetTest extends TestCase
{
    public function test_per_field_budget_does_not_starve_other_fields(): void
    {
        $big = str_repeat('Huge state payload. ', 80);
        $state = $this->stateWith([
            'big_field' => $big,
            'small_field' => 'ok',
        ]);
        $memory = MemoryConfig::fromArray(['budget_state' => 20]);
        $events = [];

        $out = StateTemplateInterpolator::interpolate(
            'Summarize: {{big_field}} using {{small_field}}',
            $state,
            $memory,
            $events,
        );

        $this->assertStringContainsString('using ok', $out);
        $this->assertStringContainsString(TokenBudgetTruncator::MARKER, $out);
        $this->assertStringNotContainsString($big, $out);
        $this->assertCount(1, $events);
        $this->assertSame('state_field', $events[0]['kind']);
        $this->assertSame('big_field', $events[0]['field']);
    }

    public function test_arrays_budgeted_on_serialized_form(): void
    {
        $items = array_fill(0, 40, str_repeat('item', 20));
        $state = $this->stateWith(['items' => $items]);
        $memory = MemoryConfig::fromArray(['budget_state' => 15]);
        $events = [];

        $out = StateTemplateInterpolator::interpolate('{{items}}', $state, $memory, $events);

        $this->assertStringContainsString(TokenBudgetTruncator::MARKER, $out);
        $this->assertSame('state_field', $events[0]['kind']);
        $this->assertLessThanOrEqual(15, (new TokenBudgetTruncator)->estimateTokens($out));
    }

    public function test_rag_field_skips_generic_state_budget(): void
    {
        $context = str_repeat('Retrieval chunk text. ', 40);
        $state = $this->stateWith(['rag_context' => ['context' => $context]]);
        $memory = MemoryConfig::fromArray(['budget_state' => 10]);
        $events = [];

        $out = StateTemplateInterpolator::interpolate('{{rag_context.context}}', $state, $memory, $events);

        $this->assertSame($context, $out);
        $this->assertSame([], $events);
    }

    public function test_rag_budget_takes_precedence_over_state_budget(): void
    {
        $context = str_repeat('Retrieval chunk text. ', 40);
        $state = $this->stateWith(['rag_context' => ['context' => $context]]);
        $memory = MemoryConfig::fromArray([
            'budget_rag' => 25,
            'budget_state' => 5,
        ]);
        $events = [];

        $out = StateTemplateInterpolator::interpolate('{{rag_context.context}}', $state, $memory, $events);

        $this->assertNotSame($context, $out);
        $this->assertSame('rag_context', $events[0]['kind']);
        $this->assertLessThanOrEqual(25, (new TokenBudgetTruncator)->estimateTokens($out));
    }

    public function test_no_state_budget_is_unchanged(): void
    {
        $state = $this->stateWith(['big_field' => str_repeat('x', 500)]);
        $out = StateTemplateInterpolator::interpolate('{{big_field}}', $state);

        $this->assertSame(str_repeat('x', 500), $out);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function stateWith(array $data): BuilderWorkflowState
    {
        return new BuilderWorkflowState(new GraphContext([], []), null, $data);
    }
}
