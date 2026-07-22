<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\NeuronAIStudioServiceProvider;
use Livewire\LivewireServiceProvider;
use NeuronAI\Laravel\NeuronAIServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            NeuronAIServiceProvider::class,
            NeuronAIStudioServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.env', 'local');
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('neuron.provider.default', 'openai');
        $app['config']->set('neuron.provider.openai', [
            'key' => 'test-key',
            'model' => 'gpt-4o-mini',
            'parameters' => [],
        ]);
        // Sync ingest in tests so Livewire assertions see completed documents immediately.
        $app['config']->set('neuronai-studio.rag.async_ingest', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
