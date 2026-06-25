<?php

namespace ElvisLopesDigital\NeuronAIStudio\Runtime\NodeExecutors;

use ElvisLopesDigital\NeuronAIStudio\Models\AgentDefinition;
use ElvisLopesDigital\NeuronAIStudio\Runtime\AgentRunner;
use ElvisLopesDigital\NeuronAIStudio\Runtime\GraphContext;
use ElvisLopesDigital\NeuronAIStudio\Runtime\MessageFactory;
use ElvisLopesDigital\NeuronAIStudio\Runtime\StateTemplateInterpolator;
use NeuronAI\Workflow\WorkflowState;

class AgentNodeExecutor implements NodeExecutorInterface
{
    public function __construct(
        protected AgentRunner $agentRunner,
        protected MessageFactory $messages,
    ) {}

    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        $data = $nodeConfig['data'] ?? [];
        $outputKey = $data['output_key'] ?? 'agent_response';
        $rawMessage = array_key_exists('message', $data)
            ? (string) $data['message']
            : (string) $state->get('input', '');

        if ($rawMessage === '') {
            $rawMessage = (string) $state->get('input', '');
        }

        $message = StateTemplateInterpolator::interpolate($rawMessage, $state);
        $attachments = is_array($state->get('attachments')) ? $state->get('attachments') : [];
        $userMessage = $this->messages->userMessage($message, $attachments);

        if (isset($data['agent_id'])) {
            $agent = AgentDefinition::findOrFail($data['agent_id']);
            $response = $this->agentRunner->runInline([
                'provider' => $agent->provider,
                'model' => $agent->model,
                'instructions' => $agent->instructions,
                'tools' => $agent->tools ?? [],
            ], $userMessage, $agent);
        } else {
            $response = $this->agentRunner->runInline($data, $userMessage);
        }

        $state->set($outputKey, $response->content);

        return 'default';
    }
}
