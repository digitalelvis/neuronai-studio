<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\ToolApprovalRequiredException;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Testing\FakeAIProvider;
use NeuronAI\Tools\Tool;
use NeuronAI\Workflow\Interrupt\ApprovalRequest;
use NeuronAI\Workflow\Interrupt\WorkflowInterrupt;
use NeuronAI\Workflow\Persistence\InMemoryPersistence;
use ReflectionMethod;

class AgentRunnerToolApprovalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TrackingApprovableToolHandler::$executed = false;
    }

    protected function makeRunner(FakeAIProvider $provider): AgentRunner
    {
        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn($provider);

        $toolResolver = $this->createMock(ToolResolver::class);
        $toolResolver->method('resolveMany')->willReturn([]);

        return new AgentRunner(
            $registry,
            $toolResolver,
            $this->createMock(McpToolResolver::class),
            new ToolEventExtractor,
            new MessageFactory,
        );
    }

    protected function approvalConfig(): array
    {
        return [
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
            'tools' => [],
            'require_tool_approval' => true,
        ];
    }

    protected function toolCall(): ToolCallMessage
    {
        $tool = Tool::make('delete_file', 'Deletes a file')
            ->setCallable(new TrackingApprovableToolHandler)
            ->setInputs(['path' => '/tmp/report.txt'])
            ->setCallId('call_1');

        return new ToolCallMessage(null, [$tool]);
    }

    public function test_run_inline_pauses_for_tool_approval_when_enabled(): void
    {
        $runner = $this->makeRunner(new FakeAIProvider($this->toolCall()));

        try {
            $runner->runInline($this->approvalConfig(), 'Delete the report');

            $this->fail('Expected ToolApprovalRequiredException was not thrown.');
        } catch (ToolApprovalRequiredException $exception) {
            $this->assertSame('', $exception->nodeId);
            $this->assertCount(1, $exception->pendingTools);
            $this->assertSame('delete_file', $exception->pendingTools[0]['name']);
            $this->assertSame(['path' => '/tmp/report.txt'], $exception->pendingTools[0]['arguments']);
            $this->assertSame('call_1', $exception->pendingTools[0]['call_id']);
        }
    }

    public function test_run_inline_does_not_pause_when_approval_disabled(): void
    {
        $runner = $this->makeRunner(new FakeAIProvider(new AssistantMessage('Just an answer')));

        $result = $runner->runInline([
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
            'tools' => [],
            'require_tool_approval' => false,
        ], 'Say hi');

        $this->assertSame('Just an answer', $result->content);
    }

    public function test_resume_approve_executes_tool(): void
    {
        $runner = $this->makeRunner(new FakeAIProvider(
            $this->toolCall(),
            new AssistantMessage('Report deleted successfully.'),
        ));

        try {
            $runner->runInline($this->approvalConfig(), 'Delete the report');
            $this->fail('Expected ToolApprovalRequiredException was not thrown.');
        } catch (ToolApprovalRequiredException $exception) {
            $result = $runner->resumeInlineApproval(
                $this->approvalConfig(),
                $exception->serializedInterrupt,
                'approve',
            );

            $this->assertTrue(TrackingApprovableToolHandler::$executed);
            $this->assertSame('Report deleted successfully.', $result->content);
        }
    }

    public function test_resume_reject_does_not_execute_tool(): void
    {
        $runner = $this->makeRunner(new FakeAIProvider(
            $this->toolCall(),
            new AssistantMessage('Understood, I will not delete anything.'),
        ));

        try {
            $runner->runInline($this->approvalConfig(), 'Delete the report');
            $this->fail('Expected ToolApprovalRequiredException was not thrown.');
        } catch (ToolApprovalRequiredException $exception) {
            $result = $runner->resumeInlineApproval(
                $this->approvalConfig(),
                $exception->serializedInterrupt,
                'reject',
                'Do not delete production data.',
            );

            $this->assertFalse(TrackingApprovableToolHandler::$executed);
            $this->assertSame('Understood, I will not delete anything.', $result->content);
        }
    }

    public function test_resume_with_pending_actions_does_not_execute_tool(): void
    {
        $runner = $this->makeRunner(new FakeAIProvider(
            $this->toolCall(),
            new AssistantMessage('Understood.'),
        ));
        $config = $this->approvalConfig();

        try {
            $runner->runInline($config, 'Delete the report');
            $this->fail('Expected ToolApprovalRequiredException was not thrown.');
        } catch (ToolApprovalRequiredException $exception) {
            $serialized = base64_decode((string) $exception->serializedInterrupt, true);
            $this->assertNotFalse($serialized);

            /** @var WorkflowInterrupt $interrupt */
            $interrupt = unserialize($serialized);
            $request = $interrupt->getRequest();
            $this->assertInstanceOf(ApprovalRequest::class, $request);

            foreach ($request->getActions() as $action) {
                $this->assertTrue($action->isPending());
            }

            $makeAgent = new ReflectionMethod(AgentRunner::class, 'makeAgent');
            $agent = $makeAgent->invoke($runner, null, $config, null);

            $persistence = new InMemoryPersistence;
            $resumeToken = 'studio_tool_approval';
            $persistence->save($resumeToken, $interrupt);
            $agent->setPersistence($persistence, $resumeToken);

            $content = $agent->chat([], $request)->getMessage()->getContent();

            $this->assertFalse(TrackingApprovableToolHandler::$executed);
            $this->assertSame('Understood.', $content);
        }
    }
}

class TrackingApprovableToolHandler
{
    public static bool $executed = false;

    public function __invoke(): string
    {
        self::$executed = true;

        return 'file deleted';
    }
}
