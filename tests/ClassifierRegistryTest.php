<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Registry\ClassifierRegistry;
use DigitalElvis\NeuronAIStudio\Registry\LayaClassifier;
use NeuronAI\Classifier\TypeSafeAI\TypeSafeAI;

class ClassifierRegistryTest extends TestCase
{
    public function test_laya_driver_uses_the_configured_url_without_a_key(): void
    {
        config([
            'neuronai-studio.classifier.driver' => 'laya',
            'neuronai-studio.classifier.url' => 'http://127.0.0.1:9000/v1/',
            'neuronai-studio.classifier.laya_key' => null,
            'neuronai-studio.classifier.key' => null,
        ]);

        $client = app(ClassifierRegistry::class)->resolve();

        $this->assertInstanceOf(LayaClassifier::class, $client);
        $this->assertSame('http://127.0.0.1:9000/v1', $client->baseUri());
    }

    public function test_laya_driver_strips_a_trailing_systemone_path(): void
    {
        config([
            'neuronai-studio.classifier.driver' => 'laya',
            'neuronai-studio.classifier.url' => 'http://classifier.test/v1/systemone',
            'neuronai-studio.classifier.laya_key' => 'laya-secret',
        ]);

        $client = app(ClassifierRegistry::class)->resolve();

        $this->assertInstanceOf(LayaClassifier::class, $client);
        $this->assertSame('http://classifier.test/v1', $client->baseUri());
    }

    public function test_typesafe_driver_fails_closed_without_a_key(): void
    {
        config([
            'neuronai-studio.classifier.driver' => 'typesafe',
            'neuronai-studio.classifier.key' => null,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TYPESAFE_KEY');

        app(ClassifierRegistry::class)->resolve();
    }

    public function test_typesafe_driver_returns_the_typesafe_client(): void
    {
        config([
            'neuronai-studio.classifier.driver' => 'typesafe',
            'neuronai-studio.classifier.key' => 'ts-key',
        ]);

        $client = app(ClassifierRegistry::class)->resolve();

        $this->assertInstanceOf(TypeSafeAI::class, $client);
        $this->assertNotInstanceOf(LayaClassifier::class, $client);
    }

    public function test_unknown_driver_fails(): void
    {
        config(['neuronai-studio.classifier.driver' => 'vendor-sdk']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown classifier driver');

        app(ClassifierRegistry::class)->resolve();
    }

    public function test_laya_driver_fails_when_the_url_is_empty(): void
    {
        config([
            'neuronai-studio.classifier.driver' => 'laya',
            'neuronai-studio.classifier.url' => '   ',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('LAYA_URL');

        app(ClassifierRegistry::class)->resolve();
    }
}
