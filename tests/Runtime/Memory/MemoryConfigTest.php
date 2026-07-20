<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Runtime\Memory;

use DigitalElvis\NeuronAIStudio\Runtime\Memory\MemoryConfig;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use InvalidArgumentException;

class MemoryConfigTest extends TestCase
{
    public function test_null_and_empty_are_inherit(): void
    {
        $fromNull = MemoryConfig::fromArray(null);
        $fromEmpty = MemoryConfig::fromArray([]);

        $this->assertTrue($fromNull->isInherit());
        $this->assertTrue($fromEmpty->isInherit());
        $this->assertNull($fromNull->toArray());
        $this->assertNull($fromEmpty->toArray());
        $this->assertNull($fromNull->contextWindow());
        $this->assertNull($fromNull->driver());
        $this->assertNull($fromNull->summarizationEnabled());
        $this->assertNull($fromNull->summarizationThreshold());
    }

    public function test_parses_valid_envelope(): void
    {
        $config = MemoryConfig::fromArray([
            'context_window' => 4000,
            'driver' => 'in_memory',
            'summarization_enabled' => true,
            'summarization_threshold' => 0.8,
            'budget_rag' => 800,
            'budget_tool_results' => 1000,
            'budget_state' => 500,
            'unknown_future_key' => 'ignored',
        ]);

        $this->assertFalse($config->isInherit());
        $this->assertSame(4000, $config->contextWindow());
        $this->assertSame(MemoryConfig::DRIVER_IN_MEMORY, $config->driver());
        $this->assertTrue($config->summarizationEnabled());
        $this->assertSame(0.8, $config->summarizationThreshold());
        $this->assertSame(800, $config->budgetRag());
        $this->assertSame(1000, $config->budgetToolResults());
        $this->assertSame(500, $config->budgetState());

        $serialized = $config->toArray();
        $this->assertSame(4000, $serialized['context_window']);
        $this->assertSame('in_memory', $serialized['driver']);
        $this->assertTrue($serialized['summarization_enabled']);
        $this->assertSame(0.8, $serialized['summarization_threshold']);
        $this->assertSame(800, $serialized['budget_rag']);
        $this->assertArrayNotHasKey('unknown_future_key', $serialized);
    }

    public function test_rejects_non_positive_context_window(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('context_window');

        MemoryConfig::fromArray(['context_window' => 0]);
    }

    public function test_rejects_negative_context_window(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('context_window');

        MemoryConfig::fromArray(['context_window' => -10]);
    }

    public function test_rejects_unknown_driver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('driver');

        MemoryConfig::fromArray(['driver' => 'redis']);
    }

    public function test_rejects_malformed_summarization_threshold(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('summarization_threshold');

        MemoryConfig::fromArray(['summarization_threshold' => 1.5]);
    }

    public function test_rejects_zero_threshold(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('summarization_threshold');

        MemoryConfig::fromArray(['summarization_threshold' => 0]);
    }

    public function test_rejects_non_positive_budget_keys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('budget_rag');

        MemoryConfig::fromArray(['budget_rag' => 0]);
    }

    public function test_partial_envelope_keeps_unset_as_null(): void
    {
        $config = MemoryConfig::fromArray([
            'context_window' => 2000,
        ]);

        $this->assertSame(2000, $config->contextWindow());
        $this->assertNull($config->driver());
        $this->assertNull($config->summarizationEnabled());
        $this->assertSame(['context_window' => 2000], $config->toArray());
    }

    public function test_merge_override_wins_over_base(): void
    {
        $base = MemoryConfig::fromArray([
            'context_window' => 4000,
            'driver' => 'eloquent',
            'summarization_enabled' => true,
        ]);
        $override = MemoryConfig::fromArray([
            'context_window' => 1000,
            'driver' => 'in_memory',
        ]);

        $merged = $base->merge($override);

        $this->assertSame(1000, $merged->contextWindow());
        $this->assertSame(MemoryConfig::DRIVER_IN_MEMORY, $merged->driver());
        $this->assertTrue($merged->summarizationEnabled());
    }

    public function test_merge_empty_override_preserves_base(): void
    {
        $base = MemoryConfig::fromArray(['context_window' => 4000, 'driver' => 'eloquent']);
        $merged = $base->merge(MemoryConfig::fromArray(null));

        $this->assertSame(4000, $merged->contextWindow());
        $this->assertSame(MemoryConfig::DRIVER_ELOQUENT, $merged->driver());
    }

    public function test_validation_rules_reject_invalid_payload(): void
    {
        $validator = validator(
            ['memory_config' => ['context_window' => 0, 'driver' => 'redis']],
            MemoryConfig::validationRules('memory_config'),
        );

        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('memory_config.context_window'));
        $this->assertTrue($validator->errors()->has('memory_config.driver'));
    }
}
