<?php

namespace ElvisLopesDigital\NeuronAIStudio\Codegen\NodeCodeGenerators;

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
        $return = $context->returnStatement($nodePlan['returnType']);

        $body = <<<PHP
        if (\$state->get({$outputKey}) === null || \$state->get({$outputKey}) === '') {
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
        }

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
