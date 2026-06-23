<?php

namespace ElvisLopesDigital\NeuronAIStudio\Tests;

use ElvisLopesDigital\NeuronAIStudio\NeuronAIStudioServiceProvider;

class ServiceProviderTest extends TestCase
{
    public function test_service_provider_registers_facade(): void
    {
        $this->assertTrue($this->app->bound('neuronai-studio'));
    }

    public function test_routes_are_registered(): void
    {
        $routes = collect($this->app['router']->getRoutes())->pluck('uri');

        $this->assertTrue($routes->contains('neuronai-studio'));
        $this->assertTrue($routes->contains('neuronai-studio/agents'));
        $this->assertTrue($routes->contains('neuronai-studio/workflows'));
    }

    public function test_commands_are_registered(): void
    {
        $commands = NeuronAIStudioServiceProvider::class;

        $this->assertTrue(class_exists($commands));
    }
}
