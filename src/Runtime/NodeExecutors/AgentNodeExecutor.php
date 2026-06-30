<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\StateTemplateInterpolator;
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
        $userMessage = $this->messages->resolveMessageWithAttachments($message, $attachments);
        $threadKey = $state->get('__studio_thread_id');
        $threadKey = is_string($threadKey) && $threadKey !== '' ? $threadKey : null;

        if (isset($data['agent_id'])) {
            $agent = AgentDefinition::findOrFail($data['agent_id']);
            $response = $this->agentRunner->runInline([
                'provider' => $agent->provider,
                'model' => $agent->model,
                'instructions' => $agent->instructions,
                'tools' => $agent->tools ?? [],
            ], $userMessage, $agent, $threadKey);
        } else {
            $response = $this->agentRunner->runInline($data, $userMessage, null, $threadKey);
        }

        $state->set($outputKey, $response->content);

        return 'default';
    }
}
