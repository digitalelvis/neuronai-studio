<?php

namespace DigitalElvis\NeuronAIStudio\Registry;

use NeuronAI\Classifier\TypeSafeAI\TypeSafeAI;

/**
 * TypeSafe-compatible client pointed at a host-operated System One endpoint.
 */
class LayaClassifier extends TypeSafeAI
{
    public function __construct(string $baseUri, string $model, string $key = '')
    {
        parent::__construct(
            key: $key !== '' ? $key : 'local',
            model: $model,
        );

        $this->baseUri = $baseUri;
    }

    public function baseUri(): string
    {
        return $this->baseUri;
    }
}
