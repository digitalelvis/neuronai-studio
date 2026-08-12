<?php

namespace DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators;

class StopNodeCodeGenerator implements NodeCodeGeneratorInterface
{
    public function supports(string $type): bool
    {
        return $type === 'stop';
    }

    public function generate(array $nodePlan, CodegenContext $context): array
    {
        $data = is_array($nodePlan['data'] ?? null) ? $nodePlan['data'] : [];
        $reply = is_string($data['reply'] ?? null) ? trim($data['reply']) : '';

        if ($reply === '') {
            return [
                'body' => 'return new StopEvent($state->all());',
                'imports' => [],
            ];
        }

        $replyLiteral = var_export($reply, true);

        $body = <<<PHP
\$__replyTemplate = {$replyLiteral};
\$state->set('reply', \\DigitalElvis\\NeuronAIStudio\\Runtime\\StateTemplateInterpolator::interpolate(\$__replyTemplate, \$state));

return new StopEvent(\$state->all());
PHP;

        return [
            'body' => $body,
            'imports' => [],
        ];
    }
}
