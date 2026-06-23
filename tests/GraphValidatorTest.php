<?php

namespace ElvisLopesDigital\NeuronAIStudio\Tests;

use ElvisLopesDigital\NeuronAIStudio\Runtime\GraphValidator;

class GraphValidatorTest extends TestCase
{
    public function test_validates_default_graph(): void
    {
        $validator = app(GraphValidator::class);
        $result = $validator->validate([
            'version' => 1,
            'nodes' => [
                ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
            ],
            'edges' => [
                ['id' => 'e1', 'source' => 'start_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
            ],
        ]);

        $this->assertTrue($result['valid']);
    }

    public function test_rejects_graph_without_start(): void
    {
        $validator = app(GraphValidator::class);
        $result = $validator->validate([
            'nodes' => [
                ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
            ],
            'edges' => [],
        ]);

        $this->assertFalse($result['valid']);
    }
}
