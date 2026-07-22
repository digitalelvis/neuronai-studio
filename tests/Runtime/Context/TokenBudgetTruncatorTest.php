<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Runtime\Context;

use DigitalElvis\NeuronAIStudio\Runtime\Context\TokenBudgetTruncator;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;

class TokenBudgetTruncatorTest extends TestCase
{
    private TokenBudgetTruncator $truncator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->truncator = new TokenBudgetTruncator;
    }

    public function test_under_budget_returns_byte_identical(): void
    {
        $text = 'Short prose that fits.';
        $result = $this->truncator->truncate($text, 1000);

        $this->assertFalse($result->truncated);
        $this->assertSame('none', $result->strategy);
        $this->assertSame($text, $result->text);
        $this->assertSame($result->tokensBefore, $result->tokensAfter);
    }

    public function test_prose_cuts_at_sentence_boundary_within_tolerance(): void
    {
        // Marker ≈ 4 tokens; budget 25 → content ≈ 84 chars; tolerance window starts ≈ 76.
        // End first sentence inside that window so strategy is "sentence".
        $sentence1 = str_repeat('Word ', 15).'ends.'; // 80 chars
        $sentence2 = ' Second sentence continues with more words here for padding and overflow.';
        $text = $sentence1.$sentence2;

        $result = $this->truncator->truncate($text, 25);

        $this->assertTrue($result->truncated);
        $this->assertSame('sentence', $result->strategy);
        $this->assertStringEndsWith(TokenBudgetTruncator::MARKER, $result->text);
        $body = substr($result->text, 0, -strlen(TokenBudgetTruncator::MARKER));
        $this->assertStringEndsWith('.', $body);
        $this->assertStringContainsString('ends.', $body);
        $this->assertStringNotContainsString('Second sentence', $body);
        $this->assertLessThanOrEqual(25, $result->tokensAfter);
        $this->assertGreaterThan($result->tokensAfter, $result->tokensBefore);
    }

    public function test_no_boundary_falls_back_to_hard_cut(): void
    {
        $text = str_repeat('abcdefghij', 40); // no sentence punctuation
        $result = $this->truncator->truncate($text, 10);

        $this->assertTrue($result->truncated);
        $this->assertSame('hard', $result->strategy);
        $this->assertStringEndsWith(TokenBudgetTruncator::MARKER, $result->text);
        $this->assertLessThanOrEqual(10, $result->tokensAfter);
    }

    public function test_json_blob_hard_cuts_with_marker(): void
    {
        $text = '{"data":"'.str_repeat('x', 500).'"}';
        $result = $this->truncator->truncate($text, 15);

        $this->assertTrue($result->truncated);
        $this->assertSame('hard', $result->strategy);
        $this->assertStringEndsWith(TokenBudgetTruncator::MARKER, $result->text);
    }

    public function test_base64_hard_cuts_with_marker(): void
    {
        $text = base64_encode(random_bytes(400));
        $result = $this->truncator->truncate($text, 12);

        $this->assertTrue($result->truncated);
        $this->assertSame('hard', $result->strategy);
        $this->assertStringEndsWith(TokenBudgetTruncator::MARKER, $result->text);
    }

    public function test_multibyte_content_never_breaks_utf8(): void
    {
        $text = str_repeat('Olá mundo 🎉 ', 80);
        $result = $this->truncator->truncate($text, 25);

        $this->assertTrue($result->truncated);
        $this->assertTrue(mb_check_encoding($result->text, 'UTF-8'));
        $this->assertSame(
            $result->text,
            mb_convert_encoding($result->text, 'UTF-8', 'UTF-8'),
        );
    }

    public function test_degenerate_budget_emits_marker_only(): void
    {
        $text = 'Anything longer than the marker itself.';
        $result = $this->truncator->truncate($text, 1);

        $this->assertTrue($result->truncated);
        $this->assertSame(TokenBudgetTruncator::MARKER, $result->text);
        $this->assertSame('hard', $result->strategy);
    }

    public function test_estimate_tokens_matches_chars_per_four(): void
    {
        $text = str_repeat('a', 40);
        $this->assertSame(10, $this->truncator->estimateTokens($text));
    }
}
