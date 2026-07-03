<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\NodeExecutors;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\StateTemplateInterpolator;
use DigitalElvis\NeuronAIStudio\Runtime\StructuredOutput\StructuredOutputResolver;
use NeuronAI\Workflow\WorkflowState;

class AgentNodeExecutor implements NodeExecutorInterface
{
    public function __construct(
        protected AgentRunner $agentRunner,
        protected MessageFactory $messages,
        protected StructuredOutputResolver $outputResolver,
    ) {}

    public function execute(array $nodeConfig, WorkflowState $state, GraphContext $context): string
    {
        $nodeId = (string) ($nodeConfig['id'] ?? 'agent');
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

        $definition = isset($data['agent_id']) ? AgentDefinition::findOrFail($data['agent_id']) : null;

        if ($data['structured'] ?? false) {
            $outputClass = $this->outputResolver->resolve((string) ($data['output_class'] ?? ''));
            $config = $definition !== null
                ? [
                    'provider' => $definition->provider,
                    'model' => $definition->model,
                    'instructions' => $definition->instructions,
                    'tools' => $definition->tools ?? [],
                ]
                : $data;

            $response = $this->agentRunner->structuredInline($config, $userMessage, $outputClass, $definition, $threadKey);
            $state->set($outputKey, $response->structured);

            return 'default';
        }

        if ($definition !== null) {
            $response = $this->agentRunner->runInline([
                'provider' => $definition->provider,
                'model' => $definition->model,
                'instructions' => $definition->instructions,
                'tools' => $definition->tools ?? [],
            ], $userMessage, $definition, $threadKey);
        } else {
            $response = $this->agentRunner->runInline($data, $userMessage, null, $threadKey);
        }

        $state->set($outputKey, $response->content);

        if ($state instanceof BuilderWorkflowState) {
            foreach ($response->toolEvents as $event) {
                $eventName = ($event['type'] ?? '') === 'result' ? 'tool_result' : 'tool_call';
                $state->emitStep($eventName, [
                    'node_id' => $nodeId,
                    'name' => $event['name'] ?? 'tool',
                    'inputs' => is_array($event['inputs'] ?? null) ? $event['inputs'] : [],
                    'result' => $event['result'] ?? null,
                ]);
            }
        }

        return 'default';
    }
}
