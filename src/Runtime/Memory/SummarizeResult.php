<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Memory;

/**
 * Typed result from HistorySummarizer — never throws to callers.
 */
final class SummarizeResult
{
    public const SOURCE_DEDICATED = 'dedicated';

    public const SOURCE_AGENT = 'agent';

    public const SOURCE_FAILED = 'failed';

    /**
     * @param  array{prompt_tokens?: int, completion_tokens?: int}|null  $usage
     */
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $summary = null,
        public readonly string $source = self::SOURCE_FAILED,
        public readonly ?string $error = null,
        public readonly ?array $usage = null,
        public readonly ?string $provider = null,
        public readonly ?string $model = null,
    ) {}

    public static function success(
        string $summary,
        string $source,
        string $provider,
        string $model,
        ?array $usage = null,
    ): self {
        return new self(
            ok: true,
            summary: $summary,
            source: $source,
            usage: $usage,
            provider: $provider,
            model: $model,
        );
    }

    public static function failure(string $error): self
    {
        return new self(
            ok: false,
            source: self::SOURCE_FAILED,
            error: $error,
        );
    }
}
