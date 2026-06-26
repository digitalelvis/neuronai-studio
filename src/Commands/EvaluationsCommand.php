<?php

namespace ElvisLopesDigital\NeuronAIStudio\Commands;

use Illuminate\Console\Command;
use NeuronAI\Evaluation\BaseEvaluator;
use NeuronAI\Evaluation\Config\ConfigLoader;
use NeuronAI\Evaluation\Config\EvaluationOutputResolver;
use NeuronAI\Evaluation\Discovery\EvaluatorDiscovery;
use NeuronAI\Evaluation\Output\OutputPipeline;
use NeuronAI\Evaluation\Runner\EvaluatorRunner;
use NeuronAI\Evaluation\Runner\EvaluatorSummary;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Throwable;

class EvaluationsCommand extends Command
{
    protected $signature = 'neuronai-studio:evaluations {--path=evaluators : Directory containing evaluator classes} {--verbose : Show verbose output}';

    protected $description = 'Run NeuronAI evaluators discovered in a directory';

    public function handle(): int
    {
        $path = $this->resolvePath((string) $this->option('path'));
        $verbose = (bool) $this->option('verbose');

        $configLoader = new ConfigLoader;
        $driverResolver = new EvaluationOutputResolver;
        $discovery = new EvaluatorDiscovery;
        $runner = new EvaluatorRunner;

        try {
            $driverConfigs = $configLoader->getOutputDrivers();
            $drivers = $driverResolver->resolve($driverConfigs);
            $pipeline = new OutputPipeline($drivers);

            $this->line('Neuron AI Evaluation Runner');
            $this->newLine();

            $evaluatorClasses = $discovery->discover($path);

            if ($evaluatorClasses === []) {
                $this->error("No evaluator classes found in: {$path}");

                return self::FAILURE;
            }

            $totalFailures = 0;
            $evaluatorCount = 1;
            $totalEvaluators = count($evaluatorClasses);
            $allResults = [];
            $totalTime = 0.0;

            foreach ($evaluatorClasses as $evaluatorClass) {
                if ($verbose) {
                    $this->line("Running {$this->shortClassName($evaluatorClass)}... [{$evaluatorCount}/{$totalEvaluators}]");
                }

                try {
                    $evaluator = $this->createEvaluator($evaluatorClass);
                    $summary = $runner->run($evaluator);

                    if (! $verbose) {
                        foreach ($summary->getResults() as $result) {
                            $this->output->write($result->isPassed() ? '.' : 'F');
                        }
                    }

                    if ($summary->hasFailures()) {
                        $totalFailures += $summary->getFailedCount();
                    }

                    $allResults = array_merge($allResults, $summary->getResults());
                    $totalTime += $summary->getTotalExecutionTime();
                } catch (Throwable $e) {
                    $this->error("Failed to run {$evaluatorClass}: ".$e->getMessage());
                    $totalFailures++;
                }

                $evaluatorCount++;
            }

            if (! $verbose) {
                $this->newLine();
            }

            $pipeline->output(new EvaluatorSummary($allResults, $totalTime));

            return $totalFailures > 0 ? self::FAILURE : self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    protected function resolvePath(string $path): string
    {
        if ($path === '' || str_starts_with($path, '/')) {
            return $path;
        }

        return base_path($path);
    }

    protected function createEvaluator(string $className): BaseEvaluator
    {
        try {
            $reflection = new ReflectionClass($className);
            $constructor = $reflection->getConstructor();

            if ($constructor === null || $constructor->getNumberOfRequiredParameters() === 0) {
                return $reflection->newInstance();
            }

            throw new RuntimeException(
                "Evaluator {$className} requires constructor parameters. ".
                'Please ensure evaluators can be instantiated without arguments.'
            );
        } catch (ReflectionException $e) {
            throw new RuntimeException("Cannot instantiate evaluator {$className}: ".$e->getMessage(), $e->getCode(), $e);
        }
    }

    protected function shortClassName(string $fullClassName): string
    {
        $parts = explode('\\', $fullClassName);

        return end($parts);
    }
}
