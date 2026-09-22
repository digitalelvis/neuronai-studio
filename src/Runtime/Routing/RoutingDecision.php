<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Routing;

/**
 * Last tier chosen by difficulty routing. The router does not expose the score.
 */
class RoutingDecision
{
    public ?string $tier = null;

    public ?string $provider = null;

    public ?string $model = null;

    public bool $classified = false;
}
