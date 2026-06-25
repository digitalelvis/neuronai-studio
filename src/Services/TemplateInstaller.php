<?php

namespace ElvisLopesDigital\NeuronAIStudio\Services;

use ElvisLopesDigital\NeuronAIStudio\Models\AgentDefinition;
use ElvisLopesDigital\NeuronAIStudio\Models\WorkflowDefinition;
use ElvisLopesDigital\NeuronAIStudio\Registry\TemplateRegistry;
use ElvisLopesDigital\NeuronAIStudio\Runtime\GraphValidator;
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

        $existing = AgentDefinition::where('slug', $slug)->first();

        if ($existing !== null) {
            return $existing;
        }

        return AgentDefinition::create([
            'name' => (string) ($meta['name'] ?? Str::headline($slug)),
            'slug' => $slug,
            'description' => (string) ($meta['description'] ?? ''),
            'provider' => (string) ($definition['provider'] ?? config('neuronai-studio.default_provider', 'openai')),
            'model' => (string) ($definition['model'] ?? config('neuronai-studio.default_model', 'gpt-4o-mini')),
            'instructions' => (string) ($definition['instructions'] ?? ''),
            'tools' => is_array($definition['tools'] ?? null) ? $definition['tools'] : [],
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

        $graph = $this->remapAgentRefs($template['graph'], $agentMap);
        $this->validator->assertValid($graph);

        $name = (string) ($meta['name'] ?? Str::headline($id));
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (WorkflowDefinition::where('slug', $slug)->exists()) {
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

            if ($ref === '' || ! isset($agentMap[$ref])) {
                throw new InvalidArgumentException("Workflow template references unknown agent: {$ref}");
            }

            $data['agent_id'] = $agentMap[$ref];
            unset($data['agent_ref']);
            $nodes[$index]['data'] = $data;
        }

        $graph['nodes'] = $nodes;

        return $graph;
    }
}
