<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Integration\ClientFacingState;
use DigitalElvis\NeuronAIStudio\Integration\JsonPatch;

class JsonPatchTest extends TestCase
{
    public function test_diff_empty_when_equal(): void
    {
        $this->assertSame([], JsonPatch::diff(['a' => 1], ['a' => 1]));
    }

    public function test_diff_add_replace_remove(): void
    {
        $ops = JsonPatch::diff(
            ['keep' => 1, 'gone' => 2, 'changed' => 'old'],
            ['keep' => 1, 'changed' => 'new', 'added' => 3],
        );

        $this->assertContains(['op' => 'replace', 'path' => '/changed', 'value' => 'new'], $ops);
        $this->assertContains(['op' => 'add', 'path' => '/added', 'value' => 3], $ops);
        $this->assertContains(['op' => 'remove', 'path' => '/gone'], $ops);
    }

    public function test_diff_nested_object(): void
    {
        $ops = JsonPatch::diff(
            ['user' => ['name' => 'a', 'age' => 1]],
            ['user' => ['name' => 'b', 'age' => 1]],
        );

        $this->assertSame([
            ['op' => 'replace', 'path' => '/user/name', 'value' => 'b'],
        ], $ops);
    }

    public function test_client_facing_state_strips_reserved_keys(): void
    {
        $visible = ClientFacingState::of([
            'reply' => 'hi',
            '__studio_thread_id' => 't1',
            'count' => 2,
        ]);

        $this->assertSame(['reply' => 'hi', 'count' => 2], $visible);
    }
}
