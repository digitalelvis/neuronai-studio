<?php

namespace ElvisLopesDigital\NeuronAIStudio\Codegen;

use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowDefinition;

class WorkflowExporter
{
    public function __construct(
        protected NativeWorkflowExporter $nativeExporter,
    ) {}

    public function preview(WorkflowDefinition $workflow): string
    {
        return $this->nativeExporter->preview($workflow);
    }

    /** @return array<int, string> */
    public function export(WorkflowDefinition $workflow): array
    {
        return $this->nativeExporter->export($workflow)['files'];
    }

    /** @return array{files: array<int, string>, preview: string, className: string, namespace: string, fqcn: string} */
    public function exportWithMeta(WorkflowDefinition $workflow): array
    {
        return $this->nativeExporter->export($workflow);
    }

    /**
     * @return array{code: string, className: string, namespace: string, fqcn: string, fileCount: int}
     */
    public function previewMeta(WorkflowDefinition $workflow): array
    {
        $build = $this->nativeExporter->build($workflow);

        return [
            'code' => $build['preview'],
            'className' => $build['className'],
            'namespace' => $build['namespace'],
            'fqcn' => $build['fqcn'],
            'fileCount' => 1 + count($build['nodeFiles']) + count($build['eventFiles']),
        ];
    }
}
