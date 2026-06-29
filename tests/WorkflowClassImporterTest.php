<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Attributes\StudioGraph;
use DigitalElvis\NeuronAIStudio\Codegen\WorkflowClassImporter;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Registry\WorkflowRegistry;
use Illuminate\Support\Facades\File;
use NeuronAI\Workflow\Workflow;

class WorkflowClassImporterTest extends TestCase
{
    public function test_imports_native_workflow_with_studio_graph_attribute(): void
    {
        $dir = app_path('Neuron/Workflows/ImporterNativeWorkflow');
        File::ensureDirectoryExists($dir);

        $graph = WorkflowDefinition::defaultGraph();
        $graphExport = var_export($graph, true);

        $classFile = $dir.'/ImporterNativeWorkflow.php';
        File::put($classFile, <<<PHP
<?php

namespace App\\Neuron\\Workflows\\ImporterNativeWorkflow;

use DigitalElvis\\NeuronAIStudio\\Attributes\\StudioGraph;
use NeuronAI\\Workflow\\Workflow;

#[StudioGraph(
    name: 'Importer Native',
    description: 'Native test',
    status: 'draft',
    graph: {$graphExport},
)]
class ImporterNativeWorkflow extends Workflow
{
    protected function nodes(): array
    {
        return [];
    }
}
PHP);

        require_once $classFile;

        $imported = app(WorkflowClassImporter::class)->fromClass(
            'App\\Neuron\\Workflows\\ImporterNativeWorkflow\\ImporterNativeWorkflow'
        );

        File::delete($classFile);
        @rmdir($dir);

        $this->assertFalse(app(WorkflowClassImporter::class)->hasError($imported));
        $this->assertSame('Importer Native', $imported['name']);
        $this->assertSame('native', $imported['format']);
    }

    public function test_registry_discovers_native_workflow_classes(): void
    {
        $dir = app_path('Neuron/Workflows/RegistryNativeWorkflow');
        File::ensureDirectoryExists($dir);

        $graph = WorkflowDefinition::defaultGraph();
        $graphExport = var_export($graph, true);

        $classFile = $dir.'/RegistryNativeWorkflow.php';
        File::put($classFile, <<<PHP
<?php

namespace App\\Neuron\\Workflows\\RegistryNativeWorkflow;

use DigitalElvis\\NeuronAIStudio\\Attributes\\StudioGraph;
use NeuronAI\\Workflow\\Workflow;

#[StudioGraph(
    name: 'Registry Native',
    description: '',
    status: 'draft',
    graph: {$graphExport},
)]
class RegistryNativeWorkflow extends Workflow
{
    protected function nodes(): array
    {
        return [];
    }
}
PHP);

        require_once $classFile;

        config([
            'neuronai-studio.workflow_scan_paths' => [app_path('Neuron')],
            'neuronai-studio.workflow_json_paths' => [],
        ]);

        $entries = app(WorkflowRegistry::class)->codeEntries();
        $refs = collect($entries)->pluck('ref')->all();

        File::delete($classFile);
        @rmdir($dir);

        $this->assertContains(
            'class:App\\Neuron\\Workflows\\RegistryNativeWorkflow\\RegistryNativeWorkflow',
            $refs
        );
    }
}
