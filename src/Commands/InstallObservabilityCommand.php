<?php

namespace DigitalElvis\NeuronAIStudio\Commands;

use Illuminate\Console\Command;

class InstallObservabilityCommand extends Command
{
    protected $signature = 'neuronai-studio:install-observability
                            {driver : Integration driver (inspector|langfuse)}';

    protected $description = 'Print env-first checklist to enable Inspector or Langfuse observability';

    public function handle(): int
    {
        $driver = strtolower((string) $this->argument('driver'));

        return match ($driver) {
            'inspector' => $this->printInspectorChecklist(),
            'langfuse' => $this->printLangfuseChecklist(),
            default => $this->invalidDriver($driver),
        };
    }

    protected function printInspectorChecklist(): int
    {
        $this->components->info('Inspector APM (Neuron native)');
        $this->newLine();
        $this->line('1. Create an account at https://inspector.dev and copy the ingestion key.');
        $this->line('2. Add to .env:');
        $this->newLine();
        $this->line('   INSPECTOR_INGESTION_KEY=your_ingestion_key');
        $this->line('   # Optional force-off:');
        $this->line('   # NEURONAI_STUDIO_INSPECTOR_ENABLED=false');
        $this->newLine();
        $this->line('3. Run an agent or workflow in the Studio playground.');
        $this->line('4. Open the Inspector dashboard — Studio attaches InspectorObserver explicitly');
        $this->line('   (fixes the EventBus gap when native TelemetryTracker is also active).');
        $this->newLine();
        $this->line('Docs: docs/guides/observability/inspector.md');

        return self::SUCCESS;
    }

    protected function printLangfuseChecklist(): int
    {
        $this->components->info('Langfuse (optional package)');
        $this->newLine();
        $this->line('1. Install the Laravel package in the host app:');
        $this->newLine();
        $this->line('   composer require axyr/laravel-langfuse');
        $this->newLine();
        $this->line('2. Add to .env:');
        $this->newLine();
        $this->line('   LANGFUSE_PUBLIC_KEY=pk-lf-...');
        $this->line('   LANGFUSE_SECRET_KEY=sk-lf-...');
        $this->line('   LANGFUSE_BASE_URL=https://cloud.langfuse.com');
        $this->line('   # LANGFUSE_HOST is accepted as an alias for BASE_URL');
        $this->line('   # Leave LANGFUSE_NEURON_AI_ENABLED unset/false — Studio owns Neuron wiring');
        $this->line('   # Optional force-off:');
        $this->line('   # NEURONAI_STUDIO_LANGFUSE_ENABLED=false');
        $this->newLine();
        $this->line('3. Run an agent/workflow — Studio attaches its own Langfuse observer');
        $this->line('   (compatible with Neuron 3.15+ branchId; do not use package NeuronAiObserver).');
        $this->newLine();
        $this->line('Docs: docs/guides/observability/langfuse.md');

        return self::SUCCESS;
    }

    protected function invalidDriver(string $driver): int
    {
        $this->components->error("Unknown driver [{$driver}]. Use inspector or langfuse.");

        return self::FAILURE;
    }
}
