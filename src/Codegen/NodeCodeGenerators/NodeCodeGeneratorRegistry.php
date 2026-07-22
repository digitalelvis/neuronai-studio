<?php

namespace DigitalElvis\NeuronAIStudio\Codegen\NodeCodeGenerators;

class NodeCodeGeneratorRegistry
{
    /** @var array<int, NodeCodeGeneratorInterface> */
    protected array $generators;

    public function __construct()
    {
        $this->generators = [
            new SetStateNodeCodeGenerator,
            new InvokeNodeCodeGenerator,
            new LlmNodeCodeGenerator,
            new AgentNodeCodeGenerator,
            new ConditionNodeCodeGenerator,
            new LoopNodeCodeGenerator,
            new ForkNodeCodeGenerator,
            new JoinNodeCodeGenerator,
            new ToolNodeCodeGenerator,
            new McpNodeCodeGenerator,
            new RagNodeCodeGenerator,
            new DelayNodeCodeGenerator,
            new HumanNodeCodeGenerator,
            new StopNodeCodeGenerator,
        ];
    }

    /**
     * @param  array{id: string, type: string, data: array<string, mixed>, returnType: string, branchReturns: array<string, string>}  $nodePlan
     * @return array{body: string, imports: array<int, string>}
     */
    public function generate(array $nodePlan, CodegenContext $context): array
    {
        $type = $nodePlan['type'];

        foreach ($this->generators as $generator) {
            if ($generator->supports($type)) {
                return $this->applyStopResult($generator->generate($nodePlan, $context), $nodePlan);
            }
        }

        throw new \InvalidArgumentException("Unsupported node type for native export: {$type}");
    }

    /**
     * When a node terminates a parallel branch (its default edge points to a
     * join node), rewrite its bare `StopEvent` return so the branch result is
     * carried to the executor and collected into the ParallelEvent.
     *
     * @param  array{body: string, imports: array<int, string>}  $generated
     * @param  array<string, mixed>  $nodePlan
     * @return array{body: string, imports: array<int, string>}
     */
    protected function applyStopResult(array $generated, array $nodePlan): array
    {
        $stopKey = $nodePlan['stopResultKey'] ?? null;

        if (! is_string($stopKey) || $stopKey === '') {
            return $generated;
        }

        if (! str_contains($generated['body'], 'return new StopEvent();')) {
            return $generated;
        }

        $replacement = 'return new StopEvent(result: $state->get('.var_export($stopKey, true).'));';
        $generated['body'] = str_replace('return new StopEvent();', $replacement, $generated['body']);

        return $generated;
    }
}
