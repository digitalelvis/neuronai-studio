<?php

namespace ElvisLopesDigital\NeuronAIStudio\Commands;

use ElvisLopesDigital\NeuronAIStudio\Evaluation\EloquentEvaluationOutput;
use ElvisLopesDigital\NeuronAIStudio\Evaluation\SuiteEvaluator;
use ElvisLopesDigital\NeuronAIStudio\Models\EvalRun;
use ElvisLopesDigital\NeuronAIStudio\Models\EvalSuite;
use ElvisLopesDigital\NeuronAIStudio\Registry\ProviderRegistry;
use Illuminate\Console\Command;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Evaluation\Runner\EvaluatorRunner;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Testing\FakeAIProvider;
use Throwable;

class EvalSuiteCommand extends Command
{
    protected $signature = 'neuronai-studio:eval {suite : Eval suite ID or slug} {--fake : Use FakeAIProvider for deterministic runs}';

    protected $description = 'Run an eval suite stored in the database';

    public function handle(): int
    {
        $suite = $this->findSuite((string) $this->argument('suite'));

        if ($suite === null) {
            $this->error('Eval suite not found.');

            return self::FAILURE;
        }

        $suite->loadMissing('agentDefinition');

        if ($this->option('fake')) {
            $this->bindFakeProvider();
        }

        $agent = $suite->agentDefinition;

        $run = EvalRun::create([
            'eval_suite_id' => $suite->id,
            'agent_definition_id' => $agent->id,
            'status' => 'running',
            'provider' => $agent->provider,
            'model' => $agent->model,
            'started_at' => now(),
        ]);

        try {
            $summary = (new EvaluatorRunner)->run(new SuiteEvaluator($suite));

            (new EloquentEvaluationOutput($run))->output($summary);

            $this->info("Eval run #{$run->id} completed.");
            $this->line("Passed: {$summary->getPassedCount()} / {$summary->getTotalCount()}");
            $this->line('Success rate: '.round($summary->getSuccessRate() * 100, 1).'%');

            return $summary->hasFailures() ? self::FAILURE : self::SUCCESS;
        } catch (Throwable $e) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
            ]);

            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    protected function findSuite(string $identifier): ?EvalSuite
    {
        if (ctype_digit($identifier)) {
            return EvalSuite::query()->find((int) $identifier);
        }

        return EvalSuite::query()->where('slug', $identifier)->first();
    }

    protected function bindFakeProvider(): void
    {
        $this->app->singleton(ProviderRegistry::class, function () {
            return new class extends ProviderRegistry
            {
                public function resolve(string $provider, ?string $model = null, array $parameters = []): AIProviderInterface
                {
                    return new FakeAIProvider(new AssistantMessage('Eval fake response'));
                }
            };
        });
    }
}
