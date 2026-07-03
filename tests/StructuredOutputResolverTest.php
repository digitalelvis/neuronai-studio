<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Registry\OutputClassRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\StructuredOutput\StructuredOutputResolver;
use DigitalElvis\NeuronAIStudio\Tests\Fixtures\Output\SampleLeadProfile;
use InvalidArgumentException;

class StructuredOutputResolverTest extends TestCase
{
    protected function fixtureScanConfig(): void
    {
        $fixturesPath = __DIR__.'/Fixtures';

        config([
            'neuronai-studio.export_path' => $fixturesPath,
            'neuronai-studio.export_namespace' => 'DigitalElvis\\NeuronAIStudio\\Tests\\Fixtures',
            'neuronai-studio.structured_output_scan_paths' => [$fixturesPath.'/Output'],
        ]);
    }

    public function test_resolve_returns_fqcn_for_valid_reference(): void
    {
        $this->fixtureScanConfig();

        $resolved = app(StructuredOutputResolver::class)->resolve(SampleLeadProfile::class);

        $this->assertSame(SampleLeadProfile::class, $resolved);
    }

    public function test_resolve_returns_fqcn_for_short_name(): void
    {
        $this->fixtureScanConfig();

        $resolved = app(StructuredOutputResolver::class)->resolve('SampleLeadProfile');

        $this->assertSame(SampleLeadProfile::class, $resolved);
    }

    public function test_resolve_throws_for_missing_or_invalid_reference(): void
    {
        $this->fixtureScanConfig();

        $resolver = app(StructuredOutputResolver::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Structured output class [MissingProfile] not found or invalid.');

        $resolver->resolve('MissingProfile');
    }

    public function test_resolve_throws_for_empty_reference(): void
    {
        $this->fixtureScanConfig();

        $resolver = app(StructuredOutputResolver::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Structured output class reference cannot be empty.');

        $resolver->resolve('   ');
    }

    public function test_resolve_rejects_class_without_schema_property_attributes(): void
    {
        $this->fixtureScanConfig();

        $resolver = app(StructuredOutputResolver::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Structured output class [DigitalElvis\\NeuronAIStudio\\Tests\\Fixtures\\Output\\PlainDataTransferObject] not found or invalid.');

        $resolver->resolve('DigitalElvis\\NeuronAIStudio\\Tests\\Fixtures\\Output\\PlainDataTransferObject');
    }
}
