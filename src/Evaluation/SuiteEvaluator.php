<?php

namespace ElvisLopesDigital\NeuronAIStudio\Evaluation;

use ElvisLopesDigital\NeuronAIStudio\Models\EvalSuite;
use ElvisLopesDigital\NeuronAIStudio\Registry\ProviderRegistry;
use ElvisLopesDigital\NeuronAIStudio\Runtime\AgentRunner;
use NeuronAI\Agent;
use NeuronAI\Evaluation\Assertions\Judges\CorrectnessJudge;
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

    protected ?Agent $judge = null;

    public function __construct(
        protected EvalSuite $suite,
    ) {
        parent::__construct();
    }

    public function setUp(): void
    {
        $this->suite->loadMissing('agentDefinition.mcpBindings');

        $config = $this->suite->judge_config;

        if (! is_array($config) || $config === []) {
            return;
        }

        $provider = (string) ($config['provider'] ?? $this->suite->agentDefinition->provider);
        $model = (string) ($config['model'] ?? $this->suite->agentDefinition->model);
        $instructions = (string) ($config['instructions'] ?? 'You are an expert evaluator.');

        $this->judge = Agent::make()
            ->setAiProvider(app(ProviderRegistry::class)->resolve($provider, $model))
            ->setInstructions($instructions);
    }

    public function getDataset(): DatasetInterface
    {
        return new ArrayDataset($this->suite->dataset ?? []);
    }

    public function run(array $datasetItem): mixed
    {
        $agent = $this->suite->agentDefinition;
        $input = $this->resolveInput($datasetItem);

        $result = app(AgentRunner::class)->run($agent, $input);
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
            'correctness' => $this->buildCorrectnessJudge(
                (string) ($assertionConfig['expected'] ?? $datasetItem['reference'] ?? ''),
                (float) ($assertionConfig['threshold'] ?? 0.7),
            ),
            default => throw new RuntimeException("Unknown assertion type: {$type}"),
        };
    }

    protected function buildCorrectnessJudge(string $expected, float $threshold): CorrectnessJudge
    {
        if ($this->judge === null) {
            throw new RuntimeException('Correctness judge requires judge_config on the eval suite.');
        }

        return new CorrectnessJudge(
            judge: $this->judge,
            expected: $expected,
            threshold: $threshold,
        );
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
