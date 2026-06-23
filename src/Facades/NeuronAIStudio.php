<?php

namespace ElvisLopesDigital\NeuronAIStudio\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \ElvisLopesDigital\NeuronAIStudio\Registry\NodeTypeRegistry nodeTypes()
 * @method static \ElvisLopesDigital\NeuronAIStudio\Registry\ProviderRegistry providers()
 * @method static void registerNode(string $type, string $nodeClass, array $meta = [])
 *
 * @see \ElvisLopesDigital\NeuronAIStudio\NeuronAIStudioManager
 */
class NeuronAIStudio extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'neuronai-studio';
    }
}
