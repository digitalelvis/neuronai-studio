<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\ToolApprovalRequiredException;
use PHPUnit\Framework\TestCase;

class ToolApprovalRequiredExceptionTest extends TestCase
{
    public function test_it_carries_node_id_pending_tools_and_message(): void
    {
        $pending = [
            ['name' => 'delete_file', 'arguments' => ['path' => '/tmp/x'], 'call_id' => 'call_1'],
        ];

        $exception = new ToolApprovalRequiredException('agent_1', $pending, '1 tool call requires approval before execution');

        $this->assertSame('agent_1', $exception->nodeId);
        $this->assertSame($pending, $exception->pendingTools);
        $this->assertSame('1 tool call requires approval before execution', $exception->approvalMessage);
        $this->assertSame('1 tool call requires approval before execution', $exception->getMessage());
    }
}
