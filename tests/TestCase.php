<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\NeuronAIStudioServiceProvider;
use Livewire\LivewireServiceProvider;
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
        // CodeGen defaults to local-only; force on so package tests pass under phpunit APP_ENV=testing.
        $app['config']->set('neuronai-studio.codegen', [
            'enabled' => true,
            'export' => true,
            'preview' => true,
        ]);
        // Sync ingest in tests so Livewire assertions see completed documents immediately.
        $app['config']->set('neuronai-studio.rag.async_ingest', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
