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
            new LlmNodeCodeGenerator,
            new AgentNodeCodeGenerator,
            new ConditionNodeCodeGenerator,
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
                return $generator->generate($nodePlan, $context);
            }
        }

        throw new \InvalidArgumentException("Unsupported node type for native export: {$type}");
    }
}
