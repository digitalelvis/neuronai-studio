<?php

namespace ElvisLopesDigital\NeuronAIStudio\Evaluation;

use ElvisLopesDigital\NeuronAIStudio\Models\AgentDefinition;
use ElvisLopesDigital\NeuronAIStudio\Models\EvalSuite;
use ElvisLopesDigital\NeuronAIStudio\Registry\ProviderRegistry;
use ElvisLopesDigital\NeuronAIStudio\Runtime\AgentRunner;
use ElvisLopesDigital\NeuronAIStudio\Runtime\DynamicAgent;
use NeuronAI\Agent;
use NeuronAI\Evaluation\Assertions\AgentJudge;
use NeuronAI\Evaluation\Assertions\Judges\CorrectnessJudge;
use NeuronAI\Evaluation\Assertions\Judges\FaithfulnessJudge;
use NeuronAI\Evaluation\Assertions\Judges\HelpfulnessJudge;
use NeuronAI\Evaluation\Assertions\Judges\RelevanceJudge;
use NeuronAI\Evaluation\Assertions\MatchesRegex;
use NeuronAI\Evaluation\Assertions\StringContains;
use NeuronAI\Evaluation\Assertions\StringContainsAll;
use NeuronAI\Evaluation\Assertions\StringContainsAny;
use NeuronAI\Evaluation\BaseEvaluator;
use NeuronAI\Evaluation\Contracts\AssertionInterface;
use NeuronAI\Evaluation\Contracts\DatasetInterface;
use NeuronAI\Evaluation\Dataset\ArrayDataset;
use RuntimeException;

class SuiteEvaluator extends BaseEvaluator
{
    /** @var array<int, array{name: string, inputs?: array<string, mixed>, result?: string|null, type?: string}> */
    protected array $lastToolEvents = [];

    protected Agent|DynamicAgent|null $judge = null;

    public function __construct(
        protected EvalSuite $suite,
        protected bool $fakeAgentProvider = false,
    ) {
        parent::__construct();
    }

    public function setUp(): void
    {
        $this->suite->loadMissing(['agentDefinition.mcpBindings', 'judgeAgent.mcpBindings']);

        $this->judge = $this->resolveJudge();
    }

    public function getDataset(): DatasetInterface
    {
        return new ArrayDataset($this->suite->dataset ?? []);
    }

    public function run(array $datasetItem): mixed
    {
        $agent = $this->suite->agentDefinition;
        $input = $this->resolveInput($datasetItem);

        $result = app(AgentRunner::class)->run($agent, $input, $this->fakeAgentProvider);
        $this->lastToolEvents = $result->toolEvents;

        return $result->content;
    }

    public function evaluate(mixed $output, array $datasetItem): void
    {
        if (isset($datasetItem['reference']) && is_string($datasetItem['reference'])) {
            $this->assert(new StringContains($datasetItem['reference']), $output);
        }

        if (isset($datasetItem['_assertions']) && is_array($datasetItem['_assertions'])) {
            foreach ($datasetItem['_assertions'] as $assertionConfig) {
                if (! is_array($assertionConfig)) {
                    continue;
                }

                $this->assert($this->buildAssertion($assertionConfig, $datasetItem), $output);
            }
        }

        if (isset($datasetItem['tool']) && is_string($datasetItem['tool'])) {
            $this->assert(new ToolWasCalled($datasetItem['tool'], $this->lastToolEvents), $output);
        }
    }

    /**
     * @param  array<string, mixed>  $assertionConfig
     * @param  array<string, mixed>  $datasetItem
     */
    protected function buildAssertion(array $assertionConfig, array $datasetItem): AssertionInterface
    {
        $type = (string) ($assertionConfig['type'] ?? '');

        return match ($type) {
            'contains' => new StringContains((string) ($assertionConfig['value'] ?? '')),
            'contains_any' => new StringContainsAny($this->stringList($assertionConfig['values'] ?? [])),
            'contains_all' => new StringContainsAll($this->stringList($assertionConfig['values'] ?? [])),
            'regex', 'matches_regex' => new MatchesRegex((string) ($assertionConfig['pattern'] ?? $assertionConfig['regex'] ?? '')),
            'correctness' => new CorrectnessJudge(
                judge: $this->requireJudge(),
                expected: (string) ($assertionConfig['expected'] ?? $datasetItem['reference'] ?? ''),
                threshold: (float) ($assertionConfig['threshold'] ?? 0.7),
            ),
            'faithfulness' => new FaithfulnessJudge(
                judge: $this->requireJudge(),
                context: (string) ($assertionConfig['context'] ?? $datasetItem['context'] ?? ''),
                threshold: (float) ($assertionConfig['threshold'] ?? 0.7),
            ),
            'relevance' => new RelevanceJudge(
                judge: $this->requireJudge(),
                question: (string) ($assertionConfig['question'] ?? $datasetItem['input'] ?? ''),
                threshold: (float) ($assertionConfig['threshold'] ?? 0.7),
            ),
            'helpfulness' => new HelpfulnessJudge(
                judge: $this->requireJudge(),
                threshold: (float) ($assertionConfig['threshold'] ?? 0.7),
            ),
            'criteria' => new AgentJudge(
                judge: $this->requireJudge(),
                criteria: (string) ($assertionConfig['criteria'] ?? ''),
                threshold: (float) ($assertionConfig['threshold'] ?? 0.7),
                reference: isset($assertionConfig['reference']) ? (string) $assertionConfig['reference'] : null,
                examples: is_array($assertionConfig['examples'] ?? null) ? $assertionConfig['examples'] : [],
            ),
            default => throw new RuntimeException("Unknown assertion type: {$type}"),
        };
    }

    protected function resolveJudge(): Agent|DynamicAgent|null
    {
        if ($this->suite->judge_agent_definition_id !== null && $this->suite->judgeAgent !== null) {
            return app(AgentRunner::class)->resolveAgent($this->suite->judgeAgent);
        }

        $config = $this->suite->judge_config;

        if (! is_array($config) || $config === []) {
            return null;
        }

        if (isset($config['agent_id'])) {
            $judgeAgent = AgentDefinition::query()->find((int) $config['agent_id']);

            if ($judgeAgent !== null) {
                return app(AgentRunner::class)->resolveAgent($judgeAgent);
            }
        }

        $provider = (string) ($config['provider'] ?? $this->suite->agentDefinition->provider);
        $model = (string) ($config['model'] ?? $this->suite->agentDefinition->model);
        $instructions = (string) ($config['instructions'] ?? 'You are an expert evaluator.');

        return Agent::make()
            ->setAiProvider(app(ProviderRegistry::class)->resolve($provider, $model))
            ->setInstructions($instructions);
    }

    protected function requireJudge(): Agent|DynamicAgent
    {
        if ($this->judge === null) {
            throw new RuntimeException('Judge assertions require a judge agent on this eval suite.');
        }

        return $this->judge;
    }

    /** @return array<int, string> */
    public static function judgeAssertionTypes(): array
    {
        return ['correctness', 'faithfulness', 'relevance', 'helpfulness', 'criteria'];
    }

    /** @param  array<int, mixed>  $dataset */
    public static function datasetRequiresJudge(array $dataset): bool
    {
        foreach ($dataset as $case) {
            if (! is_array($case)) {
                continue;
            }

            foreach ($case['_assertions'] ?? [] as $assertion) {
                if (! is_array($assertion)) {
                    continue;
                }

                if (in_array((string) ($assertion['type'] ?? ''), self::judgeAssertionTypes(), true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param  array<string, mixed>  $datasetItem */
    protected function resolveInput(array $datasetItem): string
    {
        $input = $datasetItem['input'] ?? '';

        if (is_string($input)) {
            return $input;
        }

        if (is_scalar($input)) {
            return (string) $input;
        }

        return json_encode($input, JSON_THROW_ON_ERROR);
    }

    /** @param  mixed  $values */
    protected function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter($values, fn ($value) => is_string($value) && $value !== ''));
    }
}
