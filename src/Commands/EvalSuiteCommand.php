<?php

namespace ElvisLopesDigital\NeuronAIStudio\Commands;

use ElvisLopesDigital\NeuronAIStudio\Evaluation\EloquentEvaluationOutput;
use ElvisLopesDigital\NeuronAIStudio\Evaluation\SuiteEvaluator;
use ElvisLopesDigital\NeuronAIStudio\Models\EvalRun;
use ElvisLopesDigital\NeuronAIStudio\Models\EvalSuite;
use Illuminate\Console\Command;
use NeuronAI\Evaluation\Runner\EvaluatorRunner;
use Throwable;

class EvalSuiteCommand extends Command
{
    protected $signature = 'neuronai-studio:eval {suite : Eval suite ID or slug} {--fake : Use FakeAIProvider for the agent under test only}';

    protected $description = 'Run an eval suite stored in the database';

    public function handle(): int
    {
        $suite = $this->findSuite((string) $this->argument('suite'));

        if ($suite === null) {
            $this->error('Eval suite not found.');

            return self::FAILURE;
        }

        $suite->loadMissing(['agentDefinition', 'judgeAgent']);

        if (SuiteEvaluator::datasetRequiresJudge($suite->dataset ?? []) && $suite->judge_agent_definition_id === null) {
            $this->error('This suite uses AI judge assertions but has no judge agent configured.');

            return self::FAILURE;
        }

        $agent = $suite->agentDefinition;

        $run = EvalRun::create([
            'eval_suite_id' => $suite->id,
            'agent_definition_id' => $agent->id,
            'status' => 'running',
            'provider' => $agent->provider,
            'model' => $agent->model,
            'judge_agent_definition_id' => $suite->judge_agent_definition_id,
            'judge_provider' => $suite->judgeAgent?->provider,
            'judge_model' => $suite->judgeAgent?->model,
            'started_at' => now(),
        ]);

        try {
            $summary = (new EvaluatorRunner)->run(new SuiteEvaluator(
                $suite,
                fakeAgentProvider: (bool) $this->option('fake'),
            ));

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
}
