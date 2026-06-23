<?php

namespace ElvisLopesDigital\NeuronAIStudio\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'neuronai-studio:install {--force : Overwrite existing files}';

    protected $description = 'Install the NeuronAI Studio package (publish config, migrations, assets)';

    public function handle(): int
    {
        $this->components->info('Installing NeuronAI Studio...');

        $this->call('vendor:publish', [
            '--tag' => 'neuronai-studio-config',
            '--force' => $this->option('force'),
        ]);

        $this->call('vendor:publish', [
            '--tag' => 'neuronai-studio-migrations',
            '--force' => $this->option('force'),
        ]);

        $this->call('vendor:publish', [
            '--tag' => 'neuronai-studio-views',
            '--force' => $this->option('force'),
        ]);

        $this->call('vendor:publish', [
            '--tag' => 'neuronai-studio-assets',
            '--force' => $this->option('force'),
        ]);

        if ($this->confirm('Run migrations now?', true)) {
            $this->call('migrate');
        }

        $this->newLine();
        $this->components->info('NeuronAI Studio installed successfully!');
        $this->line('Visit /'.config('neuronai-studio.route_prefix', 'neuronai-studio').' to open the dashboard.');
        $this->line('Canvas assets are pre-built. To rebuild after editing resources/js/studio-canvas/, run: npm install && npm run build');

        return self::SUCCESS;
    }
}
