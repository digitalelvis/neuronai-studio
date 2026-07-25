<?php

namespace DigitalElvis\NeuronAIStudio\Codegen;

use DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators\CodegenContext;
use DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators\NodeCodeGeneratorRegistry;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\GraphValidator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class NativeWorkflowExporter
{
    public function __construct(
        protected GraphValidator $validator,
        protected GraphTranspiler $transpiler,
        protected NodeCodeGeneratorRegistry $nodeGenerators,
        protected PhpArrayExporter $exporter,
    ) {}

    /**
     * @return array{files: array<int, string>, preview: string, className: string, namespace: string, fqcn: string}
     */
    public function export(WorkflowDefinition $workflow): array
    {
        $result = $this->build($workflow);
        $files = $this->write($result);

        return [
            'files' => $files,
            'preview' => $result['preview'],
            'className' => $result['className'],
            'namespace' => $result['namespace'],
            'fqcn' => $result['fqcn'],
        ];
    }

    public function preview(WorkflowDefinition $workflow): string
    {
        CodegenGuard::ensurePreview();

        return $this->build($workflow)['preview'];
    }

    /**
     * @return array{
     *     className: string,
     *     namespace: string,
     *     fqcn: string,
     *     preview: string,
     *     workflowContent: string,
     *     workflowPath: string,
     *     nodeFiles: array<int, array{path: string, content: string}>,
     *     eventFiles: array<int, array{path: string, content: string}>
     * }
     */
    public function build(WorkflowDefinition $workflow): array
    {
        $namespace = $this->baseNamespace();
        $className = Str::studly($workflow->slug).'Workflow';
        $graph = $workflow->graph ?? WorkflowDefinition::defaultGraph();
        $meta = [
            'name' => $workflow->name,
            'description' => (string) ($workflow->description ?? ''),
            'status' => $workflow->status,
        ];

        $this->validator->assertValid($graph);
        $plan = $this->transpiler->transpile($graph);
        $this->transpiler->assertPlan($plan);

        $workflowNamespace = "{$namespace}\\Workflows\\{$className}";
        $nodesNamespace = "{$workflowNamespace}\\Nodes";
        $eventsNamespace = "{$workflowNamespace}\\Events";

        $eventFiles = [];
        $nodeFiles = [];
        $generatedEvents = [];
        $previewSections = [];

        foreach ($plan['events'] as $event) {
            $content = $this->renderEvent($eventsNamespace, $event['className'], $event['kind'] ?? null);
            $path = $this->eventPath($className, $event['className']);
            $eventFiles[] = ['path' => $path, 'content' => $content];
            $previewSections[] = "// Events/{$event['className']}.php\n".$content;
            $generatedEvents[$event['className']] = true;
        }

        $nodeInstances = [];

        foreach ($plan['executionOrder'] as $nodeId) {
            $nodePlan = $plan['nodes'][$nodeId];
            $generated = $this->nodeGenerators->generate($nodePlan, new CodegenContext($this->exporter));
            $returnType = $nodePlan['returnType'];

            if ($returnType !== 'StopEvent' && ! str_contains($returnType, '|')) {
                $eventClass = $returnType;
                if (! isset($generatedEvents[$eventClass]) && $eventClass !== 'StartEvent') {
                    $content = $this->renderEvent($eventsNamespace, $eventClass);
                    $path = $this->eventPath($className, $eventClass);
                    $eventFiles[] = ['path' => $path, 'content' => $content];
                    $previewSections[] = "// Events/{$eventClass}.php\n".$content;
                    $generatedEvents[$eventClass] = true;
                }
            }

            $nodeContent = $this->renderNode(
                $nodesNamespace,
                $nodePlan,
                $generated,
                $eventsNamespace,
            );

            $nodePath = $this->nodePath($className, $nodePlan['className']);
            $nodeFiles[] = ['path' => $nodePath, 'content' => $nodeContent];
            $nodeInstances[] = "            new {$nodePlan['className']}(),";
            $previewSections[] = "// {$nodePlan['className']}.php\n".$nodeContent;
        }

        $workflowContent = $this->renderWorkflow(
            $workflowNamespace,
            $className,
            $meta,
            $graph,
            $nodesNamespace,
            $plan,
            implode("\n", $nodeInstances),
        );

        $workflowPath = $this->workflowPath($className);
        $previewSections = ["// {$className}.php\n".$workflowContent, ...$previewSections];

        return [
            'className' => $className,
            'namespace' => $workflowNamespace,
            'fqcn' => "{$workflowNamespace}\\{$className}",
            'preview' => implode("\n\n", $previewSections),
            'workflowContent' => $workflowContent,
            'workflowPath' => $workflowPath,
            'nodeFiles' => $nodeFiles,
            'eventFiles' => $eventFiles,
        ];
    }

    /**
     * @param  array{
     *     workflowContent: string,
     *     workflowPath: string,
     *     nodeFiles: array<int, array{path: string, content: string}>,
     *     eventFiles: array<int, array{path: string, content: string}>
     * }  $result
     * @return array<int, string>
     */
    protected function write(array $result): array
    {
        CodegenGuard::ensureExport();

        $files = [];

        File::ensureDirectoryExists(dirname($result['workflowPath']));
        File::put($result['workflowPath'], $result['workflowContent']);
        $files[] = $result['workflowPath'];

        foreach ($result['eventFiles'] as $file) {
            File::ensureDirectoryExists(dirname($file['path']));
            File::put($file['path'], $file['content']);
            $files[] = $file['path'];
        }

        foreach ($result['nodeFiles'] as $file) {
            File::ensureDirectoryExists(dirname($file['path']));
            File::put($file['path'], $file['content']);
            $files[] = $file['path'];
        }

        return $files;
    }

    protected function baseNamespace(): string
    {
        return config('neuronai-studio.export_namespace', 'App\\Neuron');
    }

    protected function exportRoot(): string
    {
        return rtrim(config('neuronai-studio.export_path', app_path('Neuron')), DIRECTORY_SEPARATOR);
    }

    protected function workflowPath(string $className): string
    {
        return $this->exportRoot()."/Workflows/{$className}/{$className}.php";
    }

    protected function nodePath(string $workflowClassName, string $nodeClassName): string
    {
        return $this->exportRoot()."/Workflows/{$workflowClassName}/Nodes/{$nodeClassName}.php";
    }

    protected function eventPath(string $workflowClassName, string $eventClassName): string
    {
        return $this->exportRoot()."/Workflows/{$workflowClassName}/Events/{$eventClassName}.php";
    }

    /**
     * @param  array{name: string, description: string, status: string}  $meta
     * @param  array<string, mixed>  $graph
     */
    protected function renderWorkflow(
        string $namespace,
        string $className,
        array $meta,
        array $graph,
        string $nodesNamespace,
        array $plan,
        string $nodeInstances,
    ): string {
        $nodeImports = collect($plan['executionOrder'])
            ->map(fn (string $nodeId) => "use {$nodesNamespace}\\{$plan['nodes'][$nodeId]['className']};")
            ->implode("\n");

        return str_replace(
            ['{{ namespace }}', '{{ className }}', '{{ name }}', '{{ description }}', '{{ status }}', '{{ graph }}', '{{ nodeImports }}', '{{ nodeInstances }}'],
            [
                $namespace,
                $className,
                $this->exporter->exportString($meta['name']),
                $this->exporter->exportString($meta['description']),
                $this->exporter->exportString($meta['status']),
                $this->exporter->exportArray($graph, 1),
                $nodeImports,
                $nodeInstances,
            ],
            file_get_contents(__DIR__.'/Stubs/native-workflow.stub')
        );
    }

    /**
     * @param  array{id: string, className: string, inputEvent: string, returnType: string}  $nodePlan
     * @param  array{body: string, imports: array<int, string>}  $generated
     */
    protected function renderNode(
        string $namespace,
        array $nodePlan,
        array $generated,
        string $eventsNamespace,
    ): string {
        $inputEventFqcn = $nodePlan['inputEvent'] === 'StartEvent'
            ? 'StartEvent'
            : "{$eventsNamespace}\\{$nodePlan['inputEvent']}";

        $returnTypeFqcn = $this->qualifyReturnType($nodePlan['returnType'], $eventsNamespace);

        $inputEvent = $this->shortTypeName($inputEventFqcn);
        $returnType = $this->shortTypeName($returnTypeFqcn);

        $branchImports = [];
        if (is_array($nodePlan['parallel'] ?? null)) {
            foreach ($nodePlan['parallel']['branches'] as $branch) {
                $branchImports[] = "{$eventsNamespace}\\{$branch['eventClass']}";
            }
        }

        $extraImports = collect($generated['imports'])
            ->merge($this->returnTypeImports($nodePlan['returnType'], $eventsNamespace))
            ->merge($branchImports)
            ->merge([
                $inputEventFqcn !== 'StartEvent' ? $inputEventFqcn : null,
            ])
            ->filter()
            ->unique()
            ->map(fn (string $import) => "use {$import};")
            ->implode("\n");

        $body = $this->indentBody($generated['body']);

        return str_replace(
            ['{{ namespace }}', '{{ className }}', '{{ nodeId }}', '{{ inputEvent }}', '{{ returnType }}', '{{ extraImports }}', '{{ body }}'],
            [
                $namespace,
                $nodePlan['className'],
                $nodePlan['id'],
                $inputEvent,
                $returnType,
                $extraImports !== '' ? "\n{$extraImports}" : '',
                $body,
            ],
            file_get_contents(__DIR__.'/Stubs/native-node.stub')
        );
    }

    protected function renderEvent(string $namespace, string $className, ?string $kind = null): string
    {
        $stub = $kind === 'parallel' ? 'native-parallel-event.stub' : 'native-event.stub';

        return str_replace(
            ['{{ namespace }}', '{{ className }}'],
            [$namespace, $className],
            file_get_contents(__DIR__.'/Stubs/'.$stub)
        );
    }

    protected function qualifyReturnType(string $returnType, string $eventsNamespace): string
    {
        if ($returnType === 'StopEvent') {
            return 'StopEvent';
        }

        if (str_contains($returnType, '|')) {
            return implode('|', array_map(
                fn (string $part) => $this->qualifyReturnType(trim($part), $eventsNamespace),
                explode('|', $returnType)
            ));
        }

        return "{$eventsNamespace}\\{$returnType}";
    }

    /** @return array<int, string> */
    protected function returnTypeImports(string $returnType, string $eventsNamespace): array
    {
        if ($returnType === 'StopEvent') {
            return [];
        }

        if (str_contains($returnType, '|')) {
            return collect(explode('|', $returnType))
                ->flatMap(fn (string $part) => $this->returnTypeImports(trim($part), $eventsNamespace))
                ->all();
        }

        return ["{$eventsNamespace}\\{$returnType}"];
    }

    protected function shortTypeName(string $type): string
    {
        if ($type === 'StartEvent' || $type === 'StopEvent') {
            return $type;
        }

        if (str_contains($type, '|')) {
            return implode('|', array_map(
                fn (string $part) => $this->shortTypeName(trim($part)),
                explode('|', $type)
            ));
        }

        if (str_contains($type, '\\')) {
            return substr($type, strrpos($type, '\\') + 1);
        }

        return $type;
    }

    protected function indentBody(string $body): string
    {
        return implode("\n", array_map(
            fn (string $line) => $line === '' ? '' : '        '.$line,
            explode("\n", trim($body))
        ));
    }
}
