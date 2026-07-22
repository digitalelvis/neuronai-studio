<?php

namespace DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators;

class HumanNodeCodeGenerator implements NodeCodeGeneratorInterface
{
    public function supports(string $type): bool
    {
        return $type === 'human';
    }

    public function generate(array $nodePlan, CodegenContext $context): array
    {
        $data = $nodePlan['data'];
        $outputKey = var_export((string) ($data['output_key'] ?? 'human_response'), true);
        $prompt = var_export((string) ($data['prompt'] ?? 'Please provide your input.'), true);
        $nodeId = var_export((string) ($nodePlan['id'] ?? ''), true);
        $return = $context->returnStatement($nodePlan['returnType']);

        $body = <<<PHP
        \$passthroughKey = \\DigitalElvis\\NeuronAIStudio\\Runtime\\NodeExecutors\\HumanNodeExecutor::PASSTHROUGH_STATE_KEY;
        \$passthroughFor = \$state->get(\$passthroughKey);
        if (\$passthroughFor === {$nodeId}) {
            \$state->set(\$passthroughKey, null);
            if (\$state->get({$outputKey}) !== null && \$state->get({$outputKey}) !== '') {
                {$return}
            }
        }

        if (\$state->get({$outputKey}) !== null && \$state->get({$outputKey}) !== '') {
            \$state->set({$outputKey}, null);
        }

        \$this->interrupt(new ApprovalRequest(
            {$prompt},
            [
                new Action(
                    id: 'submit',
                    name: 'Submit',
                    description: {$prompt},
                ),
            ],
        ));

        {$return}
PHP;

        return [
            'body' => $body,
            'imports' => [
                'NeuronAI\\Workflow\\Interrupt\\Action',
                'NeuronAI\\Workflow\\Interrupt\\ApprovalRequest',
            ],
        ];
    }
}
