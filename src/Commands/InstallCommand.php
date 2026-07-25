<?php

namespace DigitalElvis\NeuronAIStudio\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'neuronai-studio:install {--force : Overwrite existing files} {--with-views : Publish Blade views for customization}';

    protected $description = 'Install the NeuronAI Studio package (publish config, migrations, assets)';

    public function handle(): int
    {
        $this->components->info('Installing NeuronAI Studio...');

        $this->call('vendor:publish', [
            '--tag' => 'neuron-config',
            '--force' => $this->option('force'),
        ]);

        $this->call('vendor:publish', [
            '--tag' => 'neuronai-studio-config',
            '--force' => $this->option('force'),
        ]);

        $this->call('vendor:publish', [
            '--tag' => 'neuronai-studio-migrations',
            '--force' => $this->option('force'),
        ]);

        if ($this->option('with-views')) {
            $this->call('vendor:publish', [
                '--tag' => 'neuronai-studio-views',
                '--force' => $this->option('force'),
            ]);
        }

        $this->call('vendor:publish', [
            '--tag' => 'neuronai-studio-assets',
            '--force' => true,
        ]);

        if ($this->confirm('Run migrations now?', true)) {
            $this->call('migrate');
        }

        $this->newLine();
        $this->components->info('NeuronAI Studio installed successfully!');
        $this->line('Set provider credentials in .env (for example OPENAI_KEY) — see config/neuron.php.');
        $this->line('Visit /'.config('neuronai-studio.route_prefix', 'neuronai-studio').' to open the dashboard.');
        $this->line('JS assets are pre-built. To rebuild after editing resources/js/, run: npm install && npm run build && php artisan vendor:publish --tag=neuronai-studio-assets --force');
        $this->line('Views load from the package by default. Use --with-views on install (or vendor:publish --tag=neuronai-studio-views) only when customizing Blade templates.');

        return self::SUCCESS;
    }
}
