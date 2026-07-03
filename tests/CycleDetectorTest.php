<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Runtime\CycleDetector;

class CycleDetectorTest extends TestCase
{
    public function test_detects_no_cycle_in_dag(): void
    {
        $detector = new CycleDetector;
        $nodes = [
            ['id' => 'a', 'type' => 'start'],
            ['id' => 'b', 'type' => 'set_state'],
            ['id' => 'c', 'type' => 'stop'],
        ];
        $edges = [
            ['source' => 'a', 'target' => 'b', 'sourceHandle' => 'default'],
            ['source' => 'b', 'target' => 'c', 'sourceHandle' => 'default'],
        ];

        $this->assertFalse($detector->hasCycle($nodes, $edges));
        $this->assertSame([], $detector->backEdges($nodes, $edges));
    }

    public function test_detects_back_edge_in_cycle(): void
    {
        $detector = new CycleDetector;
        $nodes = [
            ['id' => 'a', 'type' => 'start'],
            ['id' => 'b', 'type' => 'set_state'],
        ];
        $edges = [
            ['source' => 'a', 'target' => 'b', 'sourceHandle' => 'default'],
            ['source' => 'b', 'target' => 'a', 'sourceHandle' => 'default'],
        ];

        $this->assertTrue($detector->hasCycle($nodes, $edges));
        $this->assertCount(1, $detector->backEdges($nodes, $edges));
    }

    public function test_marks_back_edge_to_loop_as_authorized(): void
    {
        $detector = new CycleDetector;
        $nodes = [
            ['id' => 'loop_1', 'type' => 'loop', 'data' => ['max_steps' => 3]],
            ['id' => 'work', 'type' => 'set_state'],
        ];
        $edges = [
            ['source' => 'loop_1', 'target' => 'work', 'sourceHandle' => 'continue'],
            ['source' => 'work', 'target' => 'loop_1', 'sourceHandle' => 'default'],
        ];

        $backEdges = $detector->backEdges($nodes, $edges);
        $unauthorized = $detector->unauthorizedBackEdges($backEdges, $nodes, $edges);

        $this->assertCount(1, $backEdges);
        $this->assertSame([], $unauthorized);
    }

    public function test_marks_back_edge_within_loop_body_as_authorized(): void
    {
        $detector = new CycleDetector;
        $nodes = [
            ['id' => 'loop_1', 'type' => 'loop', 'data' => ['max_steps' => 3]],
            ['id' => 'work_a', 'type' => 'set_state'],
            ['id' => 'work_b', 'type' => 'set_state'],
        ];
        $edges = [
            ['source' => 'loop_1', 'target' => 'work_a', 'sourceHandle' => 'continue'],
            ['source' => 'work_a', 'target' => 'work_b', 'sourceHandle' => 'default'],
            ['source' => 'work_b', 'target' => 'work_a', 'sourceHandle' => 'default'],
        ];

        $backEdges = $detector->backEdges($nodes, $edges);
        $unauthorized = $detector->unauthorizedBackEdges($backEdges, $nodes, $edges);

        $this->assertNotEmpty($backEdges);
        $this->assertSame([], $unauthorized);
    }
}
