<?php

namespace DigitalElvis\NeuronAIStudio\Commands;

use DigitalElvis\NeuronAIStudio\Support\StudioLocale;
use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'neuronai-studio:install {--force : Overwrite existing files} {--with-views : Publish Blade views for customization}';

    protected $description = 'Install the NeuronAI Studio package (publish config, migrations, assets)';

    public function handle(): int
    {
        StudioLocale::apply();

        $this->components->info(__('neuronai-studio::commands.install_start'));

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

        if ($this->confirm(__('neuronai-studio::commands.install_run_migrations'), true)) {
            $this->call('migrate');
        }

        $this->newLine();
        $this->components->info(__('neuronai-studio::commands.install_success'));
        $this->line(__('neuronai-studio::commands.install_set_credentials'));
        $this->line(__('neuronai-studio::commands.install_visit', [
            'prefix' => config('neuronai-studio.route_prefix', 'neuronai-studio'),
        ]));
        $this->line(__('neuronai-studio::commands.install_assets_rebuild'));
        $this->line(__('neuronai-studio::commands.install_views_note'));
        $this->newLine();
        $this->line(__('neuronai-studio::commands.install_skills'));
        $this->line(__('neuronai-studio::commands.install_skills_npx'));
        $this->line(__('neuronai-studio::commands.install_skills_docs'));

        return self::SUCCESS;
    }
}
