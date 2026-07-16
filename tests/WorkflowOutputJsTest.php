<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

class WorkflowOutputJsTest extends TestCase
{
    public function test_build_workflow_pretty_thread_falls_back_without_steps(): void
    {
        $projectRoot = dirname(__DIR__);
        $script = <<<'JS'
import { buildWorkflowPrettyThread } from './resources/js/studio-chat/utils/workflowOutput.js';

const output = {
    input: '',
    agent_response: 'Hello',
    attachments: [{ name: 'photo.jpg' }],
};

const thread = buildWorkflowPrettyThread(output, '');
const ok = thread.length === 2
    && thread[0].content === 'Attached: photo.jpg'
    && thread[1].content === 'Hello';

process.exit(ok ? 0 : 1);
JS;

        $command = 'cd '.escapeshellarg($projectRoot).' && node --input-type=module -e '.escapeshellarg($script);
        $output = [];
        $exitCode = 0;
        exec($command.' 2>&1', $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
    }

    public function test_pretty_thread_propagates_step_usage_and_formats_usage(): void
    {
        $projectRoot = dirname(__DIR__);
        $script = <<<'JS'
import { buildWorkflowPrettyThread } from './resources/js/studio-chat/utils/workflowOutput.js';
import { formatCost, formatTokens } from './resources/js/lib/formatUsage.js';

const output = {
    input: 'Hello',
    __steps: [{
        node_id: 'agent_1',
        node_type: 'agent',
        state_snapshot: { input: 'Hello', agent_response: 'Hi' },
        duration_ms: 42,
        total_tokens: 1200,
        estimated_cost: '0.125000',
        currency: 'USD',
    }],
};
const thread = buildWorkflowPrettyThread(output, 'Hello');
const usage = thread.find((entry) => entry.nodeId === 'agent_1')?.usage;
const ok = usage?.totalTokens === 1200
    && formatTokens(usage.totalTokens) === '1.2k tok'
    && formatCost(usage.estimatedCost, usage.currency) === 'USD 0.13';

process.exit(ok ? 0 : 1);
JS;

        $command = 'cd '.escapeshellarg($projectRoot).' && node --input-type=module -e '.escapeshellarg($script);
        $output = [];
        $exitCode = 0;
        exec($command.' 2>&1', $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
    }
}
