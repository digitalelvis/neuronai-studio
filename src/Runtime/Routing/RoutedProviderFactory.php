<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Routing;

use DigitalElvis\NeuronAIStudio\Registry\ClassifierRegistry;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Router\RouterProvider;
use NeuronAI\Router\Rules\DifficultyRule;

class RoutedProviderFactory
{
    public function __construct(
        protected ProviderRegistry $providers,
        protected ClassifierRegistry $classifiers,
    ) {}

    /**
     * @return array{0: AIProviderInterface, 1: RoutingDecision}
     */
    public function make(RoutingConfig $routing): array
    {
        $classifier = $this->classifiers->resolve($routing->classifierApiKey);
        $router = RouterProvider::make();
        $decision = new RoutingDecision;

        foreach ($routing->tiers as $name => $tier) {
            $router->addProvider(
                $name,
                $this->providers->resolve(
                    $tier['provider'],
                    $tier['model'],
                    [],
                    $tier['api_key'],
                ),
            );
        }

        $router->setDefaultProvider($routing->defaultTier());

        $rule = new DifficultyRule($classifier);
        if ($routing->has('easy')) {
            $rule->easy('easy', maxScore: $routing->easyMax);
        }
        if ($routing->has('medium')) {
            $rule->medium('medium', maxScore: $routing->mediumMax);
        }
        if ($routing->has('hard')) {
            $rule->hard('hard');
        }

        $router->setRule(new ObservingRoutingRule($rule, $decision, $routing));

        return [$router, $decision];
    }
}
