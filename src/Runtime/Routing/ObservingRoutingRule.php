<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Routing;

use NeuronAI\Router\Rules\DifficultyRule;
use NeuronAI\Router\Rules\RoutingRuleInterface;

/**
 * Records the tier DifficultyRule picked. The score stays inside the rule.
 */
class ObservingRoutingRule implements RoutingRuleInterface
{
    public function __construct(
        protected DifficultyRule $rule,
        protected RoutingDecision $decision,
        protected RoutingConfig $config,
    ) {}

    public function resolveProvider(string $method, array $messages, array $tools): string
    {
        $tier = $this->rule->resolveProvider($method, $messages, $tools);
        $selected = $this->config->tiers[$tier] ?? null;

        $this->decision->tier = $tier;
        $this->decision->classified = $messages !== [];
        $this->decision->provider = is_array($selected) ? $selected['provider'] : null;
        $this->decision->model = is_array($selected) ? $selected['model'] : null;

        return $tier;
    }
}
