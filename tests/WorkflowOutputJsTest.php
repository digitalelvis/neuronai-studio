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
}
