<?php

namespace DigitalElvis\NeuronAIStudio\Services;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Registry\TemplateRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\GraphValidator;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TemplateInstaller
{
    public function __construct(
        protected TemplateRegistry $registry,
        protected GraphValidator $validator,
    ) {}

    public function installAgent(string $id): AgentDefinition
    {
        $template = $this->registry->load('agent', $id);

        if ($template === null) {
            throw new InvalidArgumentException("Agent template not found: {$id}");
        }

        $meta = $template['meta'];
        $definition = $template['definition'];
        $slug = (string) ($meta['id'] ?? $id);

        $existing = AgentDefinition::findBySlug($slug);

        if ($existing !== null) {
            return $existing;
        }

        return AgentDefinition::create([
            'name' => (string) ($meta['name'] ?? Str::headline($slug)),
            'slug' => $slug,
            'description' => (string) ($meta['description'] ?? ''),
            'provider' => (string) config('neuronai-studio.default_provider', 'openai'),
            'model' => (string) config('neuronai-studio.default_model', 'gpt-4o-mini'),
            'instructions' => (string) ($definition['instructions'] ?? ''),
            'tools' => is_array($definition['tools'] ?? null) ? $definition['tools'] : [],
            'require_tool_approval' => (bool) ($definition['require_tool_approval'] ?? false),
            'memory_config' => is_array($definition['memory_config'] ?? null) ? $definition['memory_config'] : null,
            'metadata' => is_array($definition['metadata'] ?? null) ? $definition['metadata'] : null,
        ]);
    }

    public function installWorkflow(string $id): WorkflowDefinition
    {
        $template = $this->registry->load('workflow', $id);

        if ($template === null) {
            throw new InvalidArgumentException("Workflow template not found: {$id}");
        }

        $meta = $template['meta'];
        $agentRefs = is_array($meta['agents'] ?? null) ? $meta['agents'] : [];
        $agentMap = [];

        foreach ($agentRefs as $agentRef) {
            $agentRef = (string) $agentRef;

            if ($agentRef === '') {
                continue;
            }

            $agentMap[$agentRef] = $this->installAgent($agentRef)->id;
        }

        $workflowRefs = is_array($meta['workflows'] ?? null) ? $meta['workflows'] : [];
        $workflowMap = [];

        foreach ($workflowRefs as $workflowRef) {
            $workflowRef = (string) $workflowRef;

            if ($workflowRef === '') {
                continue;
            }

            $workflowMap[$workflowRef] = $this->installWorkflowDependency($workflowRef)->id;
        }

        $graph = $this->remapProviderDefaults(
            $this->remapWorkflowRefs(
                $this->remapAgentRefs($template['graph'], $agentMap),
                $workflowMap,
            ),
        );
        $this->validator->assertValid($graph);

        $name = (string) ($meta['name'] ?? Str::headline($id));
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (WorkflowDefinition::query()->inCurrentTenant()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return WorkflowDefinition::create([
            'name' => $name,
            'slug' => $slug,
            'description' => (string) ($meta['description'] ?? ''),
            'graph' => $graph,
            'status' => 'draft',
            'source' => 'studio',
            'locked' => false,
            'class_path' => null,
        ]);
    }

    /** @param  array<string, int>  $agentMap */
    protected function remapAgentRefs(array $graph, array $agentMap): array
    {
        $nodes = $graph['nodes'] ?? [];

        foreach ($nodes as $index => $node) {
            if (($node['type'] ?? '') !== 'agent') {
                continue;
            }

            $data = $node['data'] ?? [];
            $ref = (string) ($data['agent_ref'] ?? '');

            // Inline agents (Tool Mode specialists, canvas-configured supervisors) omit agent_ref.
            if ($ref === '') {
                continue;
            }

            if (! isset($agentMap[$ref])) {
                throw new InvalidArgumentException("Workflow template references unknown agent: {$ref}");
            }

            $data['agent_id'] = $agentMap[$ref];
            unset($data['agent_ref']);
            $nodes[$index]['data'] = $data;
        }

        $graph['nodes'] = $nodes;

        return $graph;
    }

    /**
     * Install a workflow template as a reusable dependency (stable slug = meta.id).
     * Used when a parent template lists `meta.workflows` and `run_workflow` nodes use `workflow_ref`.
     */
    public function installWorkflowDependency(string $id): WorkflowDefinition
    {
        $template = $this->registry->load('workflow', $id);

        if ($template === null) {
            throw new InvalidArgumentException("Workflow template not found: {$id}");
        }

        $meta = $template['meta'];
        $slug = (string) ($meta['id'] ?? $id);

        $existing = WorkflowDefinition::findBySlug($slug);
        if ($existing !== null) {
            return $existing;
        }

        $agentRefs = is_array($meta['agents'] ?? null) ? $meta['agents'] : [];
        $agentMap = [];

        foreach ($agentRefs as $agentRef) {
            $agentRef = (string) $agentRef;
            if ($agentRef === '') {
                continue;
            }
            $agentMap[$agentRef] = $this->installAgent($agentRef)->id;
        }

        $nestedWorkflowRefs = is_array($meta['workflows'] ?? null) ? $meta['workflows'] : [];
        $workflowMap = [];

        foreach ($nestedWorkflowRefs as $workflowRef) {
            $workflowRef = (string) $workflowRef;
            if ($workflowRef === '') {
                continue;
            }
            $workflowMap[$workflowRef] = $this->installWorkflowDependency($workflowRef)->id;
        }

        $graph = $this->remapProviderDefaults(
            $this->remapWorkflowRefs(
                $this->remapAgentRefs($template['graph'], $agentMap),
                $workflowMap,
            ),
        );
        $this->validator->assertValid($graph);

        return WorkflowDefinition::create([
            'name' => (string) ($meta['name'] ?? Str::headline($slug)),
            'slug' => $slug,
            'description' => (string) ($meta['description'] ?? ''),
            'graph' => $graph,
            'status' => 'draft',
            'source' => 'studio',
            'locked' => false,
            'class_path' => null,
        ]);
    }

    /** @param  array<string, int>  $workflowMap */
    protected function remapWorkflowRefs(array $graph, array $workflowMap): array
    {
        if ($workflowMap === []) {
            return $graph;
        }

        $nodes = $graph['nodes'] ?? [];

        foreach ($nodes as $index => $node) {
            if (($node['type'] ?? '') !== 'run_workflow') {
                continue;
            }

            $data = $node['data'] ?? [];
            $ref = (string) ($data['workflow_ref'] ?? '');

            if ($ref === '') {
                continue;
            }

            if (! isset($workflowMap[$ref])) {
                throw new InvalidArgumentException("Workflow template references unknown workflow: {$ref}");
            }

            $data['workflow_id'] = (string) $workflowMap[$ref];
            unset($data['workflow_ref']);
            $nodes[$index]['data'] = $data;
        }

        $graph['nodes'] = $nodes;

        return $graph;
    }

    protected function remapProviderDefaults(array $graph): array
    {
        $provider = (string) config('neuronai-studio.default_provider', 'openai');
        $model = (string) config('neuronai-studio.default_model', 'gpt-4o-mini');
        $nodes = $graph['nodes'] ?? [];

        foreach ($nodes as $index => $node) {
            if (! in_array($node['type'] ?? '', ['llm', 'rag'], true)) {
                continue;
            }

            $data = $node['data'] ?? [];
            $data['provider'] = $provider;
            $data['model'] = $model;
            $nodes[$index]['data'] = $data;
        }

        $graph['nodes'] = $nodes;

        return $graph;
    }
}
