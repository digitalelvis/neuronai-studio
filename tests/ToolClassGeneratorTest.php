<?php

namespace ElvisLopesDigital\NeuronAIStudio\Tests;

use ElvisLopesDigital\NeuronAIStudio\Codegen\ToolClassGenerator;
use ElvisLopesDigital\NeuronAIStudio\Codegen\ToolClassImporter;

class ToolClassGeneratorTest extends TestCase
{
    public function test_generates_tool_class_with_invoke_body(): void
    {
        $generated = app(ToolClassGenerator::class)->generate([
            'class_name' => 'ExampleTool',
            'tool_name' => 'example_tool',
            'description' => 'Example description',
            'input_schema' => [
                ['name' => 'query', 'type' => 'string', 'description' => 'Search query', 'required' => true],
            ],
            'invoke_body' => "return 'Result: '.\$query;",
        ]);

        $this->assertStringContainsString('class ExampleTool extends Tool', $generated);
        $this->assertStringContainsString("'example_tool'", $generated);
        $this->assertStringContainsString('function __invoke(string $query): string', $generated);
        $this->assertStringContainsString("return 'Result: '.\$query;", $generated);
    }

    public function test_importer_reads_existing_tool_class(): void
    {
        $fixture = <<<'PHP'
<?php

namespace App\Neuron\Tools;

use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

class FixtureImportTool extends Tool
{
    public function __construct()
    {
        parent::__construct(
            'fixture_import',
            'Fixture import description',
        );
    }

    protected function properties(): array
    {
        return [
            new ToolProperty(
                name: 'query',
                type: PropertyType::STRING,
                description: 'Query text',
                required: true,
            ),
        ];
    }

    public function __invoke(string $query): string
    {
        return 'Imported: '.$query;
    }
}
PHP;

        $path = sys_get_temp_dir().'/FixtureImportTool.php';
        file_put_contents($path, $fixture);

        require_once $path;

        $imported = app(ToolClassImporter::class)->fromClass('App\Neuron\Tools\FixtureImportTool');

        $this->assertSame('fixture_import', $imported['tool_name']);
        $this->assertSame('Fixture import description', $imported['description']);
        $this->assertSame('query', $imported['input_schema'][0]['name']);
        $this->assertStringContainsString('Imported:', $imported['invoke_body']);

        @unlink($path);
    }
}
