<?php

namespace ElvisLopesDigital\NeuronAIStudio\Runtime\NodeExecutors;

use ElvisLopesDigital\NeuronAIStudio\Models\AgentDefinition;
use ElvisLopesDigital\NeuronAIStudio\Runtime\AgentRunner;
use ElvisLopesDigital\NeuronAIStudio\Runtime\GraphContext;
use NeuronAI\Workflow\WorkflowState;

class AgentNodeExecutor implements NodeExecutorInterface
{
    public function __construct(
        protected AgentRunner $agentRunner,
    ) {}

    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        $data = $nodeConfig['data'] ?? [];
        $outputKey = $data['output_key'] ?? 'agent_response';
        $message = $data['message'] ?? $state->get('input', '');

        if (isset($data['agent_id'])) {
            $agent = AgentDefinition::findOrFail($data['agent_id']);
            $response = $this->agentRunner->run($agent, (string) $message);
        } else {
            $response = $this->agentRunner->runInline($data, (string) $message);
        }

        $state->set($outputKey, $response);

        return 'default';
    }
}
