<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Registry\OutputClassRegistry;
use DigitalElvis\NeuronAIStudio\Tests\Fixtures\Output\SampleLeadProfile;

class OutputClassRegistryTest extends TestCase
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

    public function test_all_discovers_classes_with_schema_property_attributes(): void
    {
        $this->fixtureScanConfig();

        $entries = app(OutputClassRegistry::class)->all();

        $this->assertCount(1, $entries);
        $this->assertSame(SampleLeadProfile::class, $entries[0]['class']);
        $this->assertSame('SampleLeadProfile', $entries[0]['label']);
        $this->assertSame('email', $entries[0]['properties'][0]['name']);
        $this->assertSame('Lead email address', $entries[0]['properties'][0]['description']);
        $this->assertTrue($entries[0]['properties'][0]['required']);
    }

    public function test_scan_ignores_abstract_classes_and_classes_without_schema_property(): void
    {
        $this->fixtureScanConfig();

        $classes = app(OutputClassRegistry::class)->scanOutputClasses();

        $this->assertSame([SampleLeadProfile::class], $classes);
    }

    public function test_structured_output_scan_paths_is_array(): void
    {
        $paths = config('neuronai-studio.structured_output_scan_paths');

        $this->assertIsArray($paths);
    }
}
