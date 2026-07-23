<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Http\Middleware\EnsureNeuronAIStudioAuthorized;
use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioChatMessage;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Support\ChatThreadKey;
use Illuminate\Support\Str;

class ChatThreadIndexApiTest extends TestCase
{
    public function test_agent_threads_index_returns_previews_and_labels(): void
    {
        $this->withoutMiddleware(EnsureNeuronAIStudioAuthorized::class);

        $agent = AgentDefinition::create([
            'name' => 'Index Agent',
            'slug' => 'index-agent',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
        ]);

        $threadId = (string) Str::uuid();
        StudioThread::create([
            'id' => $threadId,
            'entity_type' => AgentDefinition::class,
            'entity_id' => $agent->id,
        ]);

        StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => $threadId,
            'status' => 'completed',
            'input' => ['message' => 'Hi there'],
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);

        StudioChatMessage::create([
            'thread_id' => ChatThreadKey::forAgent($agent->id, $threadId),
            'role' => 'user',
            'content' => [['type' => 'text', 'content' => 'Hi there']],
        ]);

        $response = $this->getJson(route('neuronai-studio.agents.chat.threads.index', $agent));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $threadId);
        $response->assertJsonPath('data.0.preview', 'Hi there');
        $response->assertJsonPath('data.0.run_count', 1);
    }

    public function test_agent_thread_runs_endpoint(): void
    {
        $this->withoutMiddleware(EnsureNeuronAIStudioAuthorized::class);

        $agent = AgentDefinition::create([
            'name' => 'Runs Agent',
            'slug' => 'runs-agent',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
        ]);

        $threadId = (string) Str::uuid();
        StudioThread::create([
            'id' => $threadId,
            'entity_type' => AgentDefinition::class,
            'entity_id' => $agent->id,
        ]);

        $runId = (string) Str::uuid();
        StudioRun::create([
            'id' => $runId,
            'thread_id' => $threadId,
            'status' => 'completed',
            'input' => ['message' => 'Ping'],
            'prompt_tokens' => 10,
            'completion_tokens' => 5,
            'total_tokens' => 15,
            'started_at' => now()->subSeconds(5),
            'finished_at' => now(),
        ]);

        $response = $this->getJson(route('neuronai-studio.agents.chat.threads.runs', [
            'agent' => $agent->id,
            'thread' => $threadId,
        ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $runId);
        $response->assertJsonPath('data.0.status', 'completed');
        $response->assertJsonPath('data.0.total_tokens', 15);
    }

    public function test_workflow_threads_index(): void
    {
        $this->withoutMiddleware(EnsureNeuronAIStudioAuthorized::class);

        $workflow = WorkflowDefinition::create([
            'name' => 'Index Workflow',
            'slug' => 'index-workflow',
            'status' => 'draft',
        ]);

        $threadId = (string) Str::uuid();
        StudioThread::create([
            'id' => $threadId,
            'entity_type' => WorkflowDefinition::class,
            'entity_id' => $workflow->id,
        ]);

        StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => $threadId,
            'status' => 'completed',
            'input' => ['message' => 'Run me'],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $response = $this->getJson(route('neuronai-studio.workflows.chat.threads.index', $workflow));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $threadId);
        $response->assertJsonPath('data.0.run_count', 1);
    }
}
