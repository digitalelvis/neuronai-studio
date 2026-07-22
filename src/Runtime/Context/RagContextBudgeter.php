<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Context;

/**
 * Truncate RAG context joined with "\n\n---\n\n": keep whole chunks first,
 * truncate only the last included chunk.
 */
final class RagContextBudgeter
{
    public const DELIMITER = "\n\n---\n\n";

    public function __construct(
        private readonly TokenBudgetTruncator $truncator = new TokenBudgetTruncator,
    ) {}

    public function truncate(string $text, int $budgetTokens): TruncationResult
    {
        if ($text === '') {
            return new TruncationResult($text, false, 0, 0, TokenBudgetTruncator::STRATEGY_NONE);
        }

        $tokensBefore = $this->truncator->estimateTokens($text);
        if ($tokensBefore <= $budgetTokens) {
            return new TruncationResult(
                text: $text,
                truncated: false,
                tokensBefore: $tokensBefore,
                tokensAfter: $tokensBefore,
                strategy: TokenBudgetTruncator::STRATEGY_NONE,
            );
        }

        $chunks = explode(self::DELIMITER, $text);
        if (count($chunks) === 1) {
            return $this->truncator->truncate($text, $budgetTokens);
        }

        $kept = [];
        $assembled = '';
        foreach ($chunks as $index => $chunk) {
            $candidate = $kept === []
                ? $chunk
                : $assembled.self::DELIMITER.$chunk;

            if ($this->truncator->estimateTokens($candidate) <= $budgetTokens) {
                $kept[] = $chunk;
                $assembled = $candidate;

                continue;
            }

            if ($kept === []) {
                // First chunk alone exceeds budget — never emit empty context.
                return $this->truncator->truncate($chunk, $budgetTokens);
            }

            // Truncate the overflowing chunk so residual budget is used.
            $prefix = $assembled.self::DELIMITER;
            $prefixTokens = $this->truncator->estimateTokens($prefix);
            $remaining = max(1, $budgetTokens - $prefixTokens);
            $truncatedChunk = $this->truncator->truncate($chunk, $remaining);
            $out = $prefix.$truncatedChunk->text;

            return new TruncationResult(
                text: $out,
                truncated: true,
                tokensBefore: $tokensBefore,
                tokensAfter: $this->truncator->estimateTokens($out),
                strategy: $truncatedChunk->strategy,
            );
        }

        // All whole chunks fit somehow after loop (shouldn't happen if over budget).
        return new TruncationResult(
            text: $assembled,
            truncated: false,
            tokensBefore: $tokensBefore,
            tokensAfter: $this->truncator->estimateTokens($assembled),
            strategy: TokenBudgetTruncator::STRATEGY_NONE,
        );
    }
}
