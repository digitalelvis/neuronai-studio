<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\Attributes\DefineEnvironment;

class StreamAdapterRoutesTest extends TestCase
{
    public function test_integration_routes_registered_when_enabled(): void
    {
        $this->assertTrue(Route::has('neuronai-studio.integrate.agents.stream'));
        $this->assertTrue(Route::has('neuronai-studio.integrate.workflows.stream'));
        $this->assertTrue(Route::has('neuronai-studio.integrate.workflows.resume'));
    }

    public function test_integration_routes_use_configured_prefix(): void
    {
        $route = Route::getRoutes()->getByName('neuronai-studio.integrate.agents.stream');

        $this->assertNotNull($route);
        $this->assertStringStartsWith('api/neuronai/', $route->uri());
        $this->assertContains('api', $route->gatherMiddleware());
    }

    #[DefineEnvironment('disableStreamAdapters')]
    public function test_integration_routes_absent_when_disabled(): void
    {
        $this->assertFalse(Route::has('neuronai-studio.integrate.agents.stream'));
        $this->assertFalse(Route::has('neuronai-studio.integrate.workflows.stream'));
        $this->assertFalse(Route::has('neuronai-studio.integrate.workflows.resume'));
    }

    protected function disableStreamAdapters($app): void
    {
        $app['config']->set('neuronai-studio.stream_adapters.enabled', false);
    }
}
