<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Support;

use DigitalElvis\NeuronAIStudio\Support\NodeTitle;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;

class NodeTitleTest extends TestCase
{
    public function test_normalize_trims_and_rejects_whitespace_only(): void
    {
        $this->assertSame('Foo', NodeTitle::normalize('  Foo  '));
        $this->assertNull(NodeTitle::normalize('   '));
        $this->assertNull(NodeTitle::normalize(null));
    }

    public function test_uniqueness_key_is_case_insensitive(): void
    {
        $this->assertSame(NodeTitle::uniquenessKey('Agent'), NodeTitle::uniquenessKey('agent'));
    }

    public function test_slug_from_accented_title(): void
    {
        $this->assertSame(
            'QualificadorDeLead',
            NodeTitle::slug('Qualificador de Lead', 'agent', 'agent_1734'),
        );
    }

    public function test_slug_prefixes_type_when_empty_or_digit_leading(): void
    {
        $this->assertSame(
            'Agent123Foo',
            NodeTitle::slug('123 foo', 'agent', 'agent_1'),
        );

        $this->assertSame(
            'Agent1',
            NodeTitle::slug('!!!', 'agent', 'agent_1'),
        );
    }

    public function test_slug_falls_back_to_id_when_untitled(): void
    {
        $this->assertSame('Llm1', NodeTitle::slug(null, 'llm', 'llm_1'));
    }

    public function test_unique_default_increments_suffix(): void
    {
        $this->assertSame('Agent 2', NodeTitle::uniqueDefault('Agent', ['agent']));
        $this->assertSame('Agent 3', NodeTitle::uniqueDefault('Agent', ['Agent', 'Agent 2']));
    }
}
