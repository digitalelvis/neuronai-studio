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

    public function test_pretty_thread_expands_run_workflow_child_output_json(): void
    {
        $projectRoot = dirname(__DIR__);
        $script = <<<'JS'
import { buildWorkflowPrettyThread } from './resources/js/studio-chat/utils/workflowOutput.js';

const childOutput = JSON.stringify({
    input: 'oi',
    agent_response: 'Tudo bem?',
    __steps: [
        {
            node_id: 'start_1',
            node_type: 'start',
            state_snapshot: { input: 'oi' },
        },
        {
            node_id: 'agent_1',
            node_type: 'agent',
            state_snapshot: { input: 'oi', agent_response: 'Tudo bem?' },
            duration_ms: 120,
            total_tokens: 20,
            estimated_cost: '0.000100',
            currency: 'USD',
        },
        {
            node_id: 'stop_1',
            node_type: 'stop',
            state_snapshot: { input: 'oi', agent_response: 'Tudo bem?' },
        },
    ],
});

const output = {
    input: 'oi',
    child_output: childOutput,
    __steps: [
        {
            node_id: 'start_1',
            node_type: 'start',
            state_snapshot: { input: 'oi' },
        },
        {
            node_id: 'run_wf_1',
            node_type: 'run_workflow',
            state_snapshot: { input: 'oi', child_output: childOutput },
            duration_ms: 200,
        },
        {
            node_id: 'stop_1',
            node_type: 'stop',
            state_snapshot: { input: 'oi', child_output: childOutput },
        },
    ],
};

const thread = buildWorkflowPrettyThread(output, 'oi');
const agentEntry = thread.find((entry) => entry.nodeType === 'agent');
const blobEntry = thread.find((entry) => entry.nodeId === 'child_output' || entry.label === 'child_output');

const ok = thread[0]?.nodeType === 'start'
    && agentEntry?.content === 'Tudo bem?'
    && agentEntry?.label === 'run_wf_1 › agent_1'
    && agentEntry?.usage?.totalTokens === 20
    && !blobEntry;

process.exit(ok ? 0 : 1);
JS;

        $command = 'cd '.escapeshellarg($projectRoot).' && node --input-type=module -e '.escapeshellarg($script);
        $output = [];
        $exitCode = 0;
        exec($command.' 2>&1', $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
    }

    public function test_pretty_thread_expands_child_output_in_fallback_without_parent_steps(): void
    {
        $projectRoot = dirname(__DIR__);
        $script = <<<'JS'
import { buildWorkflowPrettyThread } from './resources/js/studio-chat/utils/workflowOutput.js';

const childOutput = JSON.stringify({
    input: 'oi',
    agent_response: 'Tudo bem?',
});

const thread = buildWorkflowPrettyThread({
    input: 'oi',
    child_output: childOutput,
}, 'oi');

const agentOrOutput = thread.find((entry) => entry.content === 'Tudo bem?');
const ok = thread[0]?.nodeType === 'start'
    && agentOrOutput != null
    && !thread.some((entry) => entry.label === 'child_output' && String(entry.content).includes('"agent_response"'));

process.exit(ok ? 0 : 1);
JS;

        $command = 'cd '.escapeshellarg($projectRoot).' && node --input-type=module -e '.escapeshellarg($script);
        $output = [];
        $exitCode = 0;
        exec($command.' 2>&1', $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
    }
}
