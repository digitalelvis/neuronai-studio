<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Registry\WorkflowRegistry;
use Illuminate\Support\Facades\File;

class WorkflowRegistryTest extends TestCase
{
    public function test_discovers_studio_workflow_classes_in_scan_path(): void
    {
        $dir = app_path('Neuron');
        File::ensureDirectoryExists($dir);

        $classFile = $dir.'/RegistryScanWorkflow.php';
        File::put($classFile, <<<'PHP'
<?php

namespace App\Neuron;

use DigitalElvis\NeuronAIStudio\Contracts\StudioWorkflow;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;

class RegistryScanWorkflow implements StudioWorkflow
{
    public static function studioMeta(): array
    {
        return ['name' => 'Registry Scan', 'description' => '', 'status' => 'draft'];
    }

    public static function studioGraph(): array
    {
        return WorkflowDefinition::defaultGraph();
    }
}
PHP);

        require_once $classFile;

        config([
            'neuronai-studio.workflow_scan_paths' => [$dir],
            'neuronai-studio.workflow_json_paths' => [],
            'neuronai-studio.export_namespace' => 'App\\Neuron',
        ]);

        $entries = app(WorkflowRegistry::class)->codeEntries();
        $refs = collect($entries)->pluck('ref')->all();

        File::delete($classFile);

        $this->assertContains('class:App\\Neuron\\RegistryScanWorkflow', $refs);
    }

    public function test_discovers_json_workflow_files(): void
    {
        $jsonPath = sys_get_temp_dir().'/neuron-workflows-'.uniqid();
        mkdir($jsonPath, 0777, true);

        file_put_contents($jsonPath.'/demo.json', json_encode([
            'meta' => ['name' => 'Discovered JSON'],
            'graph' => ['version' => 1, 'nodes' => [['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []]], 'edges' => []],
        ], JSON_THROW_ON_ERROR));

        config([
            'neuronai-studio.workflow_scan_paths' => [],
            'neuronai-studio.workflow_json_paths' => [$jsonPath],
        ]);

        $entries = app(WorkflowRegistry::class)->codeEntries();

        @unlink($jsonPath.'/demo.json');
        @rmdir($jsonPath);

        $this->assertTrue(collect($entries)->contains(fn (array $entry) => $entry['source'] === 'json'));
    }
}
