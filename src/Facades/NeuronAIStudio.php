<?php

namespace DigitalElvis\NeuronAIStudio\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \DigitalElvis\NeuronAIStudio\Registry\NodeTypeRegistry nodeTypes()
 * @method static \DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry providers()
 * @method static void registerNode(string $type, string $nodeClass, array $meta = [])
 *
 * @see \DigitalElvis\NeuronAIStudio\NeuronAIStudioManager
 */
class NeuronAIStudio extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'neuronai-studio';
    }
}
