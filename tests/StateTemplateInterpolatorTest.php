<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Runtime\BuilderWorkflowState;
use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;
use DigitalElvis\NeuronAIStudio\Runtime\StateTemplateInterpolator;

class StateTemplateInterpolatorTest extends TestCase
{
    protected function stateWith(array $data): BuilderWorkflowState
    {
        return new BuilderWorkflowState(new GraphContext([], []), null, $data);
    }

    public function test_interpolates_simple_top_level_keys(): void
    {
        $state = $this->stateWith(['input' => 'hello world']);

        $this->assertSame(
            'Message: hello world',
            StateTemplateInterpolator::interpolate('Message: {{input}}', $state),
        );
    }

    public function test_interpolates_dot_notation_keys(): void
    {
        $state = $this->stateWith(['lead' => ['tier' => 'gold']]);

        $this->assertSame(
            'Tier: gold',
            StateTemplateInterpolator::interpolate('Tier: {{lead.tier}}', $state),
        );
    }

    public function test_allows_surrounding_whitespace(): void
    {
        $state = $this->stateWith(['name' => 'Ada']);

        $this->assertSame(
            'Hi Ada',
            StateTemplateInterpolator::interpolate('Hi {{ name }}', $state),
        );
    }

    public function test_missing_keys_render_empty(): void
    {
        $state = $this->stateWith([]);

        $this->assertSame(
            'Value: ',
            StateTemplateInterpolator::interpolate('Value: {{missing.key}}', $state),
        );
    }

    public function test_arrays_render_as_json(): void
    {
        $state = $this->stateWith(['items' => ['a', 'b']]);

        $this->assertSame(
            'List: ["a","b"]',
            StateTemplateInterpolator::interpolate('List: {{items}}', $state),
        );
    }
}
