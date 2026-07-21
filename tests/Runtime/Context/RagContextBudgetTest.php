<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Runtime\Context;

use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\Context\TokenBudgetTruncator;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\Memory\MemoryConfig;
use DigitalElvis\NeuronAIStudio\Runtime\StateTemplateInterpolator;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;

class RagContextBudgetTest extends TestCase
{
    private const DELIM = "\n\n---\n\n";

    public function test_over_budget_keeps_whole_chunks_first_with_marker(): void
    {
        $chunk1 = str_repeat('Alpha sentence here. ', 8);
        $chunk2 = str_repeat('Beta sentence here. ', 8);
        $chunk3 = str_repeat('Gamma sentence here. ', 8);
        $context = $chunk1.self::DELIM.$chunk2.self::DELIM.$chunk3;

        $state = $this->stateWith(['rag_context' => ['context' => $context]]);
        $memory = MemoryConfig::fromArray(['budget_rag' => 40]);
        $events = [];

        $out = StateTemplateInterpolator::interpolate(
            'Docs: {{rag_context.context}}',
            $state,
            $memory,
            $events,
        );

        $this->assertStringStartsWith('Docs: ', $out);
        $body = substr($out, strlen('Docs: '));
        $this->assertStringContainsString('[truncated]', $body);
        $this->assertStringContainsString('Alpha', $body);
        $this->assertLessThanOrEqual(40, (new TokenBudgetTruncator)->estimateTokens($body));
        $this->assertNotEmpty($events);
        $this->assertSame('rag_context', $events[0]['kind']);
    }

    public function test_budget_smaller_than_one_chunk_still_emits_truncated_chunk(): void
    {
        $chunk = str_repeat('Long retrieval blob. ', 50);
        $state = $this->stateWith(['rag_context' => ['context' => $chunk]]);
        $memory = MemoryConfig::fromArray(['budget_rag' => 15]);
        $events = [];

        $out = StateTemplateInterpolator::interpolate(
            '{{rag_context.context}}',
            $state,
            $memory,
            $events,
        );

        $this->assertNotSame('', $out);
        $this->assertStringContainsString(TokenBudgetTruncator::MARKER, $out);
        $this->assertSame('rag_context', $events[0]['kind']);
    }

    public function test_no_budget_is_byte_identical_pass_through(): void
    {
        $context = "Chunk one.\n\n---\n\nChunk two.";
        $state = $this->stateWith(['rag_context' => ['context' => $context]]);

        $out = StateTemplateInterpolator::interpolate('{{rag_context.context}}', $state);

        $this->assertSame($context, $out);
    }

    public function test_under_budget_rag_is_byte_identical(): void
    {
        $context = 'Tiny chunk.';
        $state = $this->stateWith(['rag_context' => ['context' => $context]]);
        $memory = MemoryConfig::fromArray(['budget_rag' => 500]);
        $events = [];

        $out = StateTemplateInterpolator::interpolate('{{rag_context.context}}', $state, $memory, $events);

        $this->assertSame($context, $out);
        $this->assertSame([], $events);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function stateWith(array $data): BuilderWorkflowState
    {
        return new BuilderWorkflowState(new GraphContext([], []), null, $data);
    }
}
