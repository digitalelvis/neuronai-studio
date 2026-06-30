<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Evaluation;

use DigitalElvis\NeuronAIStudio\Evaluation\ToolWasCalled;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;

class ToolWasCalledTest extends TestCase
{
    public function test_passes_when_tool_was_called(): void
    {
        $assertion = new ToolWasCalled('search_docs', [
            ['name' => 'search_docs', 'inputs' => ['q' => 'hours'], 'result' => 'ok', 'type' => 'tool'],
        ]);

        $result = $assertion->evaluate('ignored output');

        $this->assertTrue($result->passed);
    }

    public function test_fails_when_tool_was_not_called(): void
    {
        $assertion = new ToolWasCalled('escalate_to_human', [
            ['name' => 'search_docs', 'inputs' => [], 'result' => null, 'type' => 'tool'],
        ]);

        $result = $assertion->evaluate('ignored output');

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('escalate_to_human', $result->message);
    }

    public function test_fails_when_no_tools_were_called(): void
    {
        $assertion = new ToolWasCalled('escalate_to_human', []);

        $result = $assertion->evaluate('ignored output');

        $this->assertFalse($result->passed);
        $this->assertStringContainsString('none', $result->message);
    }
}
