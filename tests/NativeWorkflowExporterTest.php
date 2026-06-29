<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Attributes\StudioGraph;
use DigitalElvis\NeuronAIStudio\Codegen\NativeWorkflowExporter;
use DigitalElvis\NeuronAIStudio\Codegen\WorkflowClassImporter;
use DigitalElvis\NeuronAIStudio\Codegen\WorkflowExporter;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use Illuminate\Support\Facades\File;

class NativeWorkflowExporterTest extends TestCase
{
    protected function exportPath(): string
    {
        return sys_get_temp_dir().'/neuron-native-export-'.uniqid();
    }

    /** @return array<string, mixed> */
    protected function linearGraph(): array
    {
        return [
            'version' => 1,
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                ['id' => 'set_input', 'type' => 'set_state', 'position' => ['x' => 100, 'y' => 0], 'data' => ['key' => 'message', 'from_key' => 'input']],
                ['id' => 'llm_1', 'type' => 'llm', 'position' => ['x' => 200, 'y' => 0], 'data' => [
                    'prompt' => 'Say hello to {{message}}',
                    'provider' => 'openai',
                    'model' => 'gpt-4o-mini',
                    'output_key' => 'reply',
                ]],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 300, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'set_input', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e2', 'source' => 'set_input', 'target' => 'llm_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e3', 'source' => 'llm_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
            ],
            'viewport' => ['x' => 0, 'y' => 0, 'zoom' => 1],
        ];
    }

    /** @return array<string, mixed> */
    protected function conditionGraph(): array
    {
        return [
            'version' => 1,
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                ['id' => 'cond_1', 'type' => 'condition', 'position' => ['x' => 100, 'y' => 0], 'data' => [
                    'state_key' => 'flag',
                    'operator' => 'equals',
                    'value' => 'yes',
                ]],
                ['id' => 'set_true', 'type' => 'set_state', 'position' => ['x' => 200, 'y' => -50], 'data' => ['key' => 'branch', 'value' => 'true']],
                ['id' => 'set_false', 'type' => 'set_state', 'position' => ['x' => 200, 'y' => 50], 'data' => ['key' => 'branch', 'value' => 'false']],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 300, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'cond_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e2', 'source' => 'cond_1', 'target' => 'set_true', 'sourceHandle' => 'true', 'targetHandle' => 'default'],
                ['id' => 'e3', 'source' => 'cond_1', 'target' => 'set_false', 'sourceHandle' => 'false', 'targetHandle' => 'default'],
                ['id' => 'e4', 'source' => 'set_true', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ['id' => 'e5', 'source' => 'set_false', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
            ],
            'viewport' => ['x' => 0, 'y' => 0, 'zoom' => 1],
        ];
    }

    public function test_exports_native_workflow_with_nodes_and_events(): void
    {
        $exportPath = $this->exportPath();
        config([
            'neuronai-studio.export_path' => $exportPath,
            'neuronai-studio.export_namespace' => 'App\\Neuron',
        ]);

        $workflow = WorkflowDefinition::make([
            'name' => 'Hello Flow',
            'slug' => 'hello-flow',
            'graph' => $this->linearGraph(),
            'status' => 'draft',
        ]);

        $result = app(NativeWorkflowExporter::class)->export($workflow);

        $this->assertGreaterThan(1, count($result['files']));
        $this->assertStringContainsString('extends Workflow', $result['preview']);
        $this->assertStringContainsString('extends Node', $result['preview']);
        $this->assertStringContainsString('implements Event', $result['preview']);
        $this->assertStringContainsString('StudioGraph', $result['preview']);
        $this->assertStringNotContainsString('studioGraph()', $result['preview']);
        $this->assertStringNotContainsString('implements StudioWorkflow', $result['preview']);
        $this->assertStringContainsString('function __invoke(StartEvent $event, WorkflowState $state): Llm1Event', $result['preview']);
        $this->assertStringNotContainsString('): App\\Neuron\\', $result['preview']);

        $this->cleanupExport($exportPath);
    }

    public function test_condition_graph_generates_branching_node(): void
    {
        $exportPath = $this->exportPath();
        config([
            'neuronai-studio.export_path' => $exportPath,
            'neuronai-studio.export_namespace' => 'App\\Neuron',
        ]);

        $workflow = WorkflowDefinition::make([
            'name' => 'Branch Flow',
            'slug' => 'branch-flow',
            'graph' => $this->conditionGraph(),
            'status' => 'draft',
        ]);

        $preview = app(NativeWorkflowExporter::class)->preview($workflow);

        $this->assertStringContainsString('class Cond1Node extends Node', $preview);
        $this->assertStringContainsString('SetTrueEvent', $preview);
        $this->assertStringContainsString('SetFalseEvent', $preview);

        $this->cleanupExport($exportPath);
    }

    public function test_round_trip_import_via_studio_graph_attribute(): void
    {
        $exportPath = $this->exportPath();
        config([
            'neuronai-studio.export_path' => $exportPath,
            'neuronai-studio.export_namespace' => 'App\\Neuron',
        ]);

        $graph = $this->linearGraph();
        $workflow = WorkflowDefinition::make([
            'name' => 'Round Trip',
            'slug' => 'round-trip',
            'graph' => $graph,
            'status' => 'published',
        ]);

        app(NativeWorkflowExporter::class)->export($workflow);

        $fqcn = 'App\\Neuron\\Workflows\\RoundTripWorkflow\\RoundTripWorkflow';
        require_once $exportPath.'/Workflows/RoundTripWorkflow/RoundTripWorkflow.php';

        $imported = app(WorkflowClassImporter::class)->fromClass($fqcn);

        $this->assertFalse(app(WorkflowClassImporter::class)->hasError($imported));
        $this->assertSame('Round Trip', $imported['name']);
        $this->assertSame('native', $imported['format']);
        $this->assertSame($graph['nodes'][1]['id'], $imported['graph']['nodes'][1]['id']);

        $this->cleanupExport($exportPath);
    }

    public function test_preview_does_not_write_files(): void
    {
        $exportPath = $this->exportPath();
        config([
            'neuronai-studio.export_path' => $exportPath,
            'neuronai-studio.export_namespace' => 'App\\Neuron',
        ]);

        $workflow = WorkflowDefinition::make([
            'name' => 'Preview Only',
            'slug' => 'preview-only',
            'graph' => WorkflowDefinition::defaultGraph(),
            'status' => 'draft',
        ]);

        app(NativeWorkflowExporter::class)->preview($workflow);

        $this->assertFalse(is_dir($exportPath));

        @rmdir($exportPath);
    }

    protected function cleanupExport(string $exportPath): void
    {
        if (! is_dir($exportPath)) {
            return;
        }

        $files = File::allFiles($exportPath);
        foreach ($files as $file) {
            @unlink($file->getPathname());
        }

        $dirs = collect(File::directories($exportPath))
            ->merge([$exportPath.'/Workflows'])
            ->sortByDesc(fn (string $dir) => strlen($dir));

        foreach ($dirs as $dir) {
            if (is_dir($dir)) {
                @rmdir($dir);
            }
        }

        @rmdir($exportPath);
    }
}
