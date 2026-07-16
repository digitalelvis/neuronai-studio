<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Usage;

use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\Attributes\DefineEnvironment;

class UsageExportRoutesTest extends TestCase
{
    public function test_usage_routes_registered_when_enabled(): void
    {
        $this->assertTrue(Route::has('neuronai-studio.usage.aggregate'));
        $this->assertTrue(Route::has('neuronai-studio.usage.runs.show'));
    }

    public function test_usage_routes_use_stream_adapters_prefix_fallback(): void
    {
        $route = Route::getRoutes()->getByName('neuronai-studio.usage.aggregate');

        $this->assertNotNull($route);
        $this->assertStringStartsWith('api/neuronai/', $route->uri());
        $this->assertContains('api', $route->gatherMiddleware());
    }

    #[DefineEnvironment('disableUsageExport')]
    public function test_usage_routes_absent_when_disabled(): void
    {
        $this->assertFalse(Route::has('neuronai-studio.usage.aggregate'));
        $this->assertFalse(Route::has('neuronai-studio.usage.runs.show'));
    }

    #[DefineEnvironment('exportOnStreamOff')]
    public function test_usage_routes_register_when_stream_adapters_disabled(): void
    {
        $this->assertFalse(Route::has('neuronai-studio.integrate.agents.stream'));
        $this->assertTrue(Route::has('neuronai-studio.usage.aggregate'));
    }

    protected function disableUsageExport($app): void
    {
        $app['config']->set('neuronai-studio.usage.export.enabled', false);
    }

    protected function exportOnStreamOff($app): void
    {
        $app['config']->set('neuronai-studio.stream_adapters.enabled', false);
        $app['config']->set('neuronai-studio.usage.export.enabled', true);
        $app['config']->set('neuronai-studio.stream_adapters.middleware', [SubstituteBindings::class]);
        $app['config']->set('neuronai-studio.usage.export.middleware', [SubstituteBindings::class]);
    }
}
