<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\Exceptions\ThreadOwnerMismatchException;
use DigitalElvis\NeuronAIStudio\Runtime\MessageFactory;
use DigitalElvis\NeuronAIStudio\Runtime\McpToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\ToolEventExtractor;
use DigitalElvis\NeuronAIStudio\Runtime\ToolResolver;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use DigitalElvis\NeuronAIStudio\Services\ChatThreadIndex;
use DigitalElvis\NeuronAIStudio\StudioInvoke;
use DigitalElvis\NeuronAIStudio\Support\ThreadOwner;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Testing\FakeAIProvider;

class ThreadOwnerAssociationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('thread_owner_customers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    public function test_thread_owner_binds_and_rejects_mismatch(): void
    {
        $a = ThreadOwnerCustomer::query()->create(['name' => 'A']);
        $b = ThreadOwnerCustomer::query()->create(['name' => 'B']);

        $thread = StudioThread::query()->create(['id' => (string) Str::uuid()]);

        ThreadOwner::fromModel($a)->bindTo($thread);
        $thread->refresh();

        $this->assertSame($a->getMorphClass(), $thread->ownerable_type);
        $this->assertSame((string) $a->id, $thread->ownerable_id);

        ThreadOwner::fromModel($a)->bindTo($thread); // same — ok

        $this->expectException(ThreadOwnerMismatchException::class);
        ThreadOwner::fromModel($b)->bindTo($thread);
    }

    public function test_workflow_run_stamps_owner_state_and_persists_on_thread(): void
    {
        $customer = ThreadOwnerCustomer::query()->create(['name' => 'Cust']);
        $workflow = WorkflowDefinition::create([
            'name' => 'Owner Flow',
            'slug' => 'owner-flow',
            'graph' => [
                'version' => 1,
                'nodes' => [
                    ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                    ['id' => 'set_1', 'type' => 'set_state', 'position' => ['x' => 200, 'y' => 0], 'data' => [
                        'key' => 'owner_snapshot',
                        'value' => '{{__studio_owner_type}}|{{__studio_owner_id}}',
                    ]],
                    ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 400, 'y' => 0], 'data' => []],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'start_1', 'target' => 'set_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                    ['id' => 'e2', 'source' => 'set_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ],
            ],
        ]);

        $threadId = (string) Str::uuid();

        $run = StudioInvoke::workflow($workflow)
            ->forOwner($customer)
            ->onThread($threadId)
            ->run(['message' => 'hi']);

        $this->assertSame('completed', $run->status);
        $this->assertSame(
            $customer->getMorphClass().'|'.$customer->id,
            $run->output['owner_snapshot'] ?? null,
        );
        $this->assertSame($customer->getMorphClass(), $run->output[ThreadOwner::TYPE_STATE_KEY] ?? null);
        $this->assertSame((string) $customer->id, $run->output[ThreadOwner::ID_STATE_KEY] ?? null);

        $thread = StudioThread::query()->findOrFail($threadId);
        $this->assertSame($customer->getMorphClass(), $thread->ownerable_type);
        $this->assertSame((string) $customer->id, $thread->ownerable_id);
    }

    public function test_workflow_hydrates_owner_state_from_existing_thread(): void
    {
        $customer = ThreadOwnerCustomer::query()->create(['name' => 'Hydrate']);
        $workflow = WorkflowDefinition::create([
            'name' => 'Hydrate Owner Flow',
            'slug' => 'hydrate-owner-flow',
            'graph' => [
                'version' => 1,
                'nodes' => [
                    ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                    ['id' => 'set_1', 'type' => 'set_state', 'position' => ['x' => 200, 'y' => 0], 'data' => [
                        'key' => 'owner_id_copy',
                        'value' => '{{__studio_owner_id}}',
                    ]],
                    ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 400, 'y' => 0], 'data' => []],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'start_1', 'target' => 'set_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                    ['id' => 'e2', 'source' => 'set_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ],
            ],
        ]);

        $threadId = (string) Str::uuid();
        StudioThread::query()->create([
            'id' => $threadId,
            'entity_type' => WorkflowDefinition::class,
            'entity_id' => $workflow->id,
            'ownerable_type' => $customer->getMorphClass(),
            'ownerable_id' => (string) $customer->id,
        ]);

        $run = app(WorkflowRunner::class)->run($workflow, [
            'message' => 'again',
            'thread_id' => $threadId,
        ]);

        $this->assertSame('completed', $run->status);
        $this->assertSame((string) $customer->id, $run->output['owner_id_copy'] ?? null);
    }

    public function test_agent_run_assigns_owner_on_thread(): void
    {
        $customer = ThreadOwnerCustomer::query()->create(['name' => 'Agent Owner']);
        $agent = AgentDefinition::create([
            'name' => 'Owner Agent',
            'slug' => 'owner-agent',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'You are helpful.',
        ]);

        $provider = new FakeAIProvider(new AssistantMessage('ok'));
        $registry = $this->createMock(ProviderRegistry::class);
        $registry->method('resolve')->willReturn($provider);

        $this->app->instance(ProviderRegistry::class, $registry);
        $this->app->instance(ToolResolver::class, $this->createMock(ToolResolver::class));
        $this->app->instance(McpToolResolver::class, $this->createMock(McpToolResolver::class));
        $this->app->instance(ToolEventExtractor::class, new ToolEventExtractor);
        $this->app->instance(MessageFactory::class, new MessageFactory);

        $threadId = (string) Str::uuid();

        StudioInvoke::agent($agent)
            ->forOwner($customer)
            ->onThread($threadId)
            ->run(['message' => 'hi']);

        $thread = StudioThread::query()->findOrFail($threadId);
        $this->assertSame($customer->getMorphClass(), $thread->ownerable_type);
        $this->assertSame((string) $customer->id, (string) $thread->ownerable_id);
    }

    public function test_list_for_owner_filters_threads(): void
    {
        $customer = ThreadOwnerCustomer::query()->create(['name' => 'Listed']);
        $other = ThreadOwnerCustomer::query()->create(['name' => 'Other']);

        $workflow = WorkflowDefinition::create([
            'name' => 'List Owner Flow',
            'slug' => 'list-owner-flow',
            'graph' => WorkflowDefinition::defaultGraph(),
        ]);

        $mine = StudioThread::query()->create([
            'id' => (string) Str::uuid(),
            'entity_type' => WorkflowDefinition::class,
            'entity_id' => $workflow->id,
            'ownerable_type' => $customer->getMorphClass(),
            'ownerable_id' => (string) $customer->id,
        ]);

        StudioThread::query()->create([
            'id' => (string) Str::uuid(),
            'entity_type' => WorkflowDefinition::class,
            'entity_id' => $workflow->id,
            'ownerable_type' => $other->getMorphClass(),
            'ownerable_id' => (string) $other->id,
        ]);

        $listed = app(ChatThreadIndex::class)->listForOwner($customer);

        $this->assertCount(1, $listed);
        $this->assertSame($mine->id, $listed[0]['id']);
        $this->assertSame((string) $customer->id, $listed[0]['ownerable_id']);
    }

    public function test_playground_without_owner_leaves_thread_unowned(): void
    {
        $workflow = WorkflowDefinition::create([
            'name' => 'Anon Flow',
            'slug' => 'anon-owner-flow',
            'graph' => [
                'version' => 1,
                'nodes' => [
                    ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                    ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 200, 'y' => 0], 'data' => []],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'start_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ],
            ],
        ]);

        $run = app(WorkflowRunner::class)->run($workflow, ['message' => 'anon']);

        $thread = StudioThread::query()->findOrFail($run->thread_id);
        $this->assertNull($thread->ownerable_type);
        $this->assertNull($thread->ownerable_id);
        $this->assertArrayNotHasKey(ThreadOwner::TYPE_STATE_KEY, $run->output ?? []);
    }
}

class ThreadOwnerCustomer extends Model
{
    protected $table = 'thread_owner_customers';

    protected $guarded = [];
}
