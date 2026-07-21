<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Context;

/**
 * Mechanical token-budget truncation consistent with Neuron TokenCounter (~4 chars/token).
 */
final class TokenBudgetTruncator
{
    public const MARKER = "\n\n[truncated]";

    public const STRATEGY_NONE = 'none';

    public const STRATEGY_SENTENCE = 'sentence';

    public const STRATEGY_HARD = 'hard';

    /** @var float Fraction of the cut point to search backward for a sentence boundary. */
    private const SENTENCE_TOLERANCE = 0.10;

    public function __construct(
        private readonly float $charsPerToken = 4.0,
    ) {}

    public function estimateTokens(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        return (int) ceil(mb_strlen($text) / $this->charsPerToken);
    }

    public function truncate(string $text, int $budgetTokens): TruncationResult
    {
        $tokensBefore = $this->estimateTokens($text);

        if ($budgetTokens >= 1 && $tokensBefore <= $budgetTokens) {
            return new TruncationResult(
                text: $text,
                truncated: false,
                tokensBefore: $tokensBefore,
                tokensAfter: $tokensBefore,
                strategy: self::STRATEGY_NONE,
            );
        }

        $markerTokens = $this->estimateTokens(self::MARKER);
        if ($budgetTokens < 1 || $budgetTokens <= $markerTokens) {
            return new TruncationResult(
                text: self::MARKER,
                truncated: true,
                tokensBefore: $tokensBefore,
                tokensAfter: $this->estimateTokens(self::MARKER),
                strategy: self::STRATEGY_HARD,
            );
        }

        $contentBudget = $budgetTokens - $markerTokens;
        $maxChars = max(1, (int) floor($contentBudget * $this->charsPerToken));

        $cut = $this->cutAtSentenceBoundary($text, $maxChars);
        if ($cut !== null) {
            $out = $cut.self::MARKER;

            return new TruncationResult(
                text: $out,
                truncated: true,
                tokensBefore: $tokensBefore,
                tokensAfter: $this->estimateTokens($out),
                strategy: self::STRATEGY_SENTENCE,
            );
        }

        $hard = mb_substr($text, 0, $maxChars).self::MARKER;

        return new TruncationResult(
            text: $hard,
            truncated: true,
            tokensBefore: $tokensBefore,
            tokensAfter: $this->estimateTokens($hard),
            strategy: self::STRATEGY_HARD,
        );
    }

    /**
     * Prefer a sentence-ending punctuation within tolerance of the cut point.
     */
    private function cutAtSentenceBoundary(string $text, int $maxChars): ?string
    {
        $length = mb_strlen($text);
        if ($maxChars >= $length) {
            return $text;
        }

        $windowStart = max(0, (int) floor($maxChars * (1 - self::SENTENCE_TOLERANCE)));
        $slice = mb_substr($text, 0, $maxChars);
        $lastBoundary = null;

        $offset = 0;
        $sliceLen = mb_strlen($slice);
        while ($offset < $sliceLen) {
            $char = mb_substr($slice, $offset, 1);
            if (in_array($char, ['.', '!', '?'], true)) {
                $pos = $offset + 1;
                if ($pos >= $windowStart) {
                    $lastBoundary = $pos;
                }
            }
            $offset++;
        }

        if ($lastBoundary === null || $lastBoundary < 1) {
            return null;
        }

        return mb_substr($text, 0, $lastBoundary);
    }
}
