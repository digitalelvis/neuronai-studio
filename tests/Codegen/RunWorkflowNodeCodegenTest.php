<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Codegen;

use DigitalElvis\NeuronAIStudio\Codegen\NativeWorkflowExporter;
use DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators\CodegenContext;
use DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators\RunWorkflowNodeCodeGenerator;
use DigitalElvis\NeuronAIStudio\Codegen\PhpArrayExporter;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;

class RunWorkflowNodeCodegenTest extends TestCase
{
    public function test_step_mode_emits_workflow_runner_call(): void
    {
        $generator = new RunWorkflowNodeCodeGenerator;
        $context = new CodegenContext(new PhpArrayExporter);

        $result = $generator->generate([
            'data' => [
                'workflow_id' => '42',
                'message' => '{{input}}',
                'state_map' => [
                    ['key' => 'lead_id', 'value' => '{{lead_id}}'],
                ],
                'output_key' => 'child_output',
            ],
            'returnType' => 'StopEvent',
        ], $context);

        $this->assertStringContainsString('WorkflowRunner::class', $result['body']);
        $this->assertStringContainsString("find('42')", $result['body']);
        $this->assertStringContainsString("\$state->set('child_output'", $result['body']);
        $this->assertStringContainsString("\$childState['lead_id']", $result['body']);
        $this->assertStringContainsString('human interrupt', $result['body']);
        $this->assertStringContainsString('return new StopEvent()', $result['body']);
    }

    public function test_step_mode_requires_workflow_id(): void
    {
        $generator = new RunWorkflowNodeCodeGenerator;
        $context = new CodegenContext(new PhpArrayExporter);

        $result = $generator->generate([
            'data' => [],
            'returnType' => 'StopEvent',
        ], $context);

        $this->assertStringContainsString('requires data.workflow_id', $result['body']);
    }

    public function test_native_export_step_mode_includes_run_workflow_node(): void
    {
        $exportPath = sys_get_temp_dir().'/neuronai-run-workflow-codegen-'.uniqid();
        config([
            'neuronai-studio.export_path' => $exportPath,
            'neuronai-studio.export_namespace' => 'App\\Neuron',
        ]);

        $child = WorkflowDefinition::create([
            'name' => 'Codegen Child',
            'slug' => 'codegen-child-'.uniqid(),
            'graph' => WorkflowDefinition::defaultGraph(),
        ]);

        $workflow = WorkflowDefinition::make([
            'name' => 'Run Workflow Step Flow',
            'slug' => 'run-workflow-step-flow',
            'graph' => [
                'version' => 1,
                'nodes' => [
                    ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                    [
                        'id' => 'run_1',
                        'type' => 'run_workflow',
                        'position' => ['x' => 100, 'y' => 0],
                        'data' => [
                            'workflow_id' => (string) $child->id,
                            'message' => '{{input}}',
                            'output_key' => 'child_output',
                            'tool_mode' => false,
                        ],
                    ],
                    ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 220, 'y' => 0], 'data' => []],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'start_1', 'target' => 'run_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                    ['id' => 'e2', 'source' => 'run_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ],
                'viewport' => ['x' => 0, 'y' => 0, 'zoom' => 1],
            ],
            'status' => 'draft',
        ]);

        $preview = app(NativeWorkflowExporter::class)->preview($workflow);

        $this->assertStringContainsString('class Run1Node', $preview);
        $this->assertStringContainsString('WorkflowRunner::class', $preview);
        $this->assertStringContainsString('child_output', $preview);
        $this->assertStringNotContainsString('Unsupported node type for native export: run_workflow', $preview);

        $this->cleanupExport($exportPath);
    }

    public function test_native_export_tool_mode_snapshots_workflow_as_tool(): void
    {
        $exportPath = sys_get_temp_dir().'/neuronai-run-workflow-tool-codegen-'.uniqid();
        config([
            'neuronai-studio.export_path' => $exportPath,
            'neuronai-studio.export_namespace' => 'App\\Neuron',
        ]);

        $child = WorkflowDefinition::create([
            'name' => 'Codegen Tool Child',
            'slug' => 'codegen-tool-child-'.uniqid(),
            'graph' => WorkflowDefinition::defaultGraph(),
        ]);

        $workflow = WorkflowDefinition::make([
            'name' => 'Run Workflow Tool Flow',
            'slug' => 'run-workflow-tool-flow',
            'graph' => [
                'version' => 1,
                'nodes' => [
                    ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                    [
                        'id' => 'supervisor_1',
                        'type' => 'agent',
                        'position' => ['x' => 100, 'y' => 0],
                        'data' => [
                            'config_mode' => 'inline',
                            'provider' => 'openai',
                            'model' => 'gpt-4o-mini',
                            'instructions' => 'You coordinate.',
                            'output_key' => 'agent_response',
                        ],
                    ],
                    [
                        'id' => 'run_tool_1',
                        'type' => 'run_workflow',
                        'position' => ['x' => 100, 'y' => 120],
                        'data' => [
                            'workflow_id' => (string) $child->id,
                            'message' => 'default',
                            'tool_mode' => true,
                            'tool_exposure' => [
                                'slug' => 'run_pricing_flow',
                                'description' => 'Run pricing',
                            ],
                        ],
                    ],
                    ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 220, 'y' => 0], 'data' => []],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'start_1', 'target' => 'supervisor_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                    ['id' => 'e2', 'source' => 'supervisor_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                    ['id' => 'e3', 'source' => 'run_tool_1', 'target' => 'supervisor_1', 'sourceHandle' => 'toolset', 'targetHandle' => 'tools'],
                ],
                'viewport' => ['x' => 0, 'y' => 0, 'zoom' => 1],
            ],
            'status' => 'draft',
        ]);

        $preview = app(NativeWorkflowExporter::class)->preview($workflow);

        $this->assertStringNotContainsString('class RunTool1Node', $preview);
        $this->assertStringContainsString('WorkflowAsTool', $preview);
        $this->assertStringContainsString('run_pricing_flow', $preview);
        $this->assertStringContainsString((string) $child->id, $preview);
        $this->assertStringContainsString('class Supervisor1Node', $preview);

        $this->cleanupExport($exportPath);
    }

    protected function cleanupExport(string $exportPath): void
    {
        if (! is_dir($exportPath)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($exportPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($exportPath);
    }
}
