<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Integration\RunAgentInputParser;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

class RunAgentInputParserTest extends TestCase
{
    protected function parser(): RunAgentInputParser
    {
        return $this->app->make(RunAgentInputParser::class);
    }

    public function test_maps_last_user_message_and_ids(): void
    {
        $request = Request::create('/agui', 'POST', [
            'threadId' => 't-abc',
            'runId' => 'r-1',
            'messages' => [
                ['id' => 'm1', 'role' => 'assistant', 'content' => 'prev'],
                ['id' => 'm2', 'role' => 'user', 'content' => 'hello copilot'],
            ],
            'tools' => [['name' => 'frontend_tool']],
            'state' => ['foo' => 'bar'],
            'context' => [['description' => 'x', 'value' => 'y']],
        ]);

        $parsed = $this->parser()->parse($request);

        $this->assertSame('t-abc', $parsed['thread_id']);
        $this->assertSame('r-1', $parsed['run_id']);
        $this->assertSame('hello copilot', $parsed['message']);
        $this->assertSame(['foo' => 'bar'], $parsed['state']);
        $this->assertSame([], $parsed['resume']);
    }

    public function test_fallback_message_body(): void
    {
        $request = Request::create('/agui', 'POST', [
            'message' => 'legacy hello',
            'thread_id' => '550e8400-e29b-41d4-a716-446655440000',
        ]);

        $parsed = $this->parser()->parse($request);

        $this->assertSame('legacy hello', $parsed['message']);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $parsed['thread_id']);
        $this->assertNotEmpty($parsed['run_id']);
    }

    public function test_message_content_parts(): void
    {
        $request = Request::create('/agui', 'POST', [
            'messages' => [
                ['role' => 'user', 'content' => [
                    ['type' => 'text', 'text' => 'part-a'],
                    ['type' => 'text', 'text' => 'part-b'],
                ]],
            ],
        ]);

        $parsed = $this->parser()->parse($request);

        $this->assertSame('part-apart-b', $parsed['message']);
    }

    public function test_cancelled_resume_is_422(): void
    {
        $request = Request::create('/agui', 'POST', [
            'threadId' => 't1',
            'resume' => [
                ['interruptId' => 'x', 'status' => 'cancelled'],
            ],
        ]);

        try {
            $this->parser()->parse($request, requireContent: false);
            $this->fail('Expected HttpResponseException');
        } catch (HttpResponseException $exception) {
            $this->assertSame(422, $exception->getResponse()->getStatusCode());
        }
    }

    public function test_resume_payload_message(): void
    {
        $request = Request::create('/agui', 'POST', [
            'threadId' => 't1',
            'resume' => [[
                'interruptId' => '550e8400-e29b-41d4-a716-446655440099',
                'status' => 'resolved',
                'payload' => ['message' => 'order-7'],
            ]],
        ]);

        $parsed = $this->parser()->parse($request, requireContent: false);

        $this->assertSame('order-7', $this->parser()->resumeMessage($parsed['resume']));
        $this->assertSame('550e8400-e29b-41d4-a716-446655440099', $this->parser()->interruptId($parsed['resume']));
    }

    public function test_agui_context_list_is_not_workflow_state(): void
    {
        $request = Request::create('/agui', 'POST', [
            'message' => 'hi',
            'context' => [
                ['description' => 'user', 'value' => 'elvis'],
            ],
        ]);

        $parsed = $this->parser()->parse($request);

        $this->assertSame([], $parsed['state']);
    }
}
