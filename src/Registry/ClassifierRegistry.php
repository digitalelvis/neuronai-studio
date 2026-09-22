<?php

namespace DigitalElvis\NeuronAIStudio\Registry;

use DigitalElvis\NeuronAIStudio\Runtime\ConfigValueResolver;
use InvalidArgumentException;
use NeuronAI\Classifier\ClassifierInterface;
use NeuronAI\Classifier\TypeSafeAI\TypeSafeAI;

class ClassifierRegistry
{
    public function __construct(
        protected ConfigValueResolver $values,
    ) {}

    /**
     * A bound ClassifierInterface (tests bind FakeClassifier) wins over the network client.
     */
    public function resolve(?string $keyOverride = null): ClassifierInterface
    {
        if (app()->bound(ClassifierInterface::class)) {
            $bound = app(ClassifierInterface::class);
            if ($bound instanceof ClassifierInterface) {
                return $bound;
            }
        }

        $driver = strtolower(trim((string) config('neuronai-studio.classifier.driver', 'typesafe')));
        $model = trim((string) config('neuronai-studio.classifier.model', 'jev-latest'));
        if ($model === '') {
            $model = 'jev-latest';
        }

        $key = $this->resolveKey($keyOverride, $driver);

        if ($driver === 'laya') {
            $url = $this->normalizeBaseUri((string) config('neuronai-studio.classifier.url', ''));
            if ($url === '') {
                throw new InvalidArgumentException(
                    'Laya classifier URL is not configured. Set LAYA_URL to the System One base URL (without /systemone).'
                );
            }

            return new LayaClassifier($url, $model, $key);
        }

        if ($driver !== 'typesafe') {
            throw new InvalidArgumentException("Unknown classifier driver [{$driver}]. Use typesafe or laya.");
        }

        if ($key === '') {
            throw new InvalidArgumentException(
                'TypeSafe classifier is not configured. Set TYPESAFE_KEY in your .env file, '
                .'or bind a Credential variable (var:NAME) on the node or agent.'
            );
        }

        return new TypeSafeAI(key: $key, model: $model);
    }

    protected function resolveKey(?string $keyOverride, string $driver): string
    {
        if (is_string($keyOverride) && trim($keyOverride) !== '') {
            $resolved = $this->values->resolve($keyOverride);

            return is_string($resolved) ? trim($resolved) : '';
        }

        $configured = $driver === 'laya'
            ? config('neuronai-studio.classifier.laya_key')
            : config('neuronai-studio.classifier.key');

        return is_string($configured) ? trim($configured) : '';
    }

    protected function normalizeBaseUri(string $url): string
    {
        $url = rtrim(trim($url), '/');
        if (str_ends_with(strtolower($url), '/systemone')) {
            $url = substr($url, 0, -strlen('/systemone'));
            $url = rtrim($url, '/');
        }

        return $url;
    }
}
