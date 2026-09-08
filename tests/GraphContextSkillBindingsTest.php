<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Runtime\GraphContext;

class GraphContextSkillBindingsTest extends TestCase
{
    public function test_resolves_skill_bindings_from_skills_edges(): void
    {
        $context = new GraphContext(
            [
                [
                    'id' => 'agent_1',
                    'type' => 'agent',
                    'data' => ['config_mode' => 'inline'],
                ],
                [
                    'id' => 'skill_1',
                    'type' => 'skill',
                    'data' => ['skill_ref' => 'skill:db:42'],
                ],
            ],
            [
                ['source' => 'skill_1', 'target' => 'agent_1', 'sourceHandle' => 'default', 'targetHandle' => 'skills'],
            ],
        );

        $this->assertSame(
            [['ref' => 'skill:db:42']],
            $context->skillBindingsFor('agent_1'),
        );
    }

    public function test_target_for_handle_skips_skills_binding_edges(): void
    {
        $context = new GraphContext(
            [
                ['id' => 'skill_1', 'type' => 'skill', 'data' => []],
                ['id' => 'agent_1', 'type' => 'agent', 'data' => []],
                ['id' => 'stop_1', 'type' => 'stop', 'data' => []],
            ],
            [
                ['source' => 'skill_1', 'target' => 'agent_1', 'sourceHandle' => 'default', 'targetHandle' => 'skills'],
                ['source' => 'skill_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
            ],
        );

        $this->assertSame('stop_1', $context->targetForHandle('skill_1'));
    }
}
