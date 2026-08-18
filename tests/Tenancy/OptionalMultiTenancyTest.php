<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Tenancy;

use DigitalElvis\NeuronAIStudio\Http\Middleware\EnsureNeuronAIStudioAuthorized;
use DigitalElvis\NeuronAIStudio\Jobs\RunWorkflowJob;
use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\KnowledgeBase;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use DigitalElvis\NeuronAIStudio\Models\Variable;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Repositories\VariableRepository;
use DigitalElvis\NeuronAIStudio\Runtime\Rag\VectorStoreFactory;
use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use DigitalElvis\NeuronAIStudio\Tenancy\StudioTenancy;
use DigitalElvis\NeuronAIStudio\Tenancy\TenantRequiredException;
use DigitalElvis\NeuronAIStudio\Tenancy\TenantResolver;
use DigitalElvis\NeuronAIStudio\Tests\Support\MutableTenantResolver;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OptionalMultiTenancyTest extends TestCase
{
    protected function tearDown(): void
    {
        MutableTenantResolver::$id = null;
        StudioTenancy::reset();
        parent::tearDown();
    }

    public function test_tenant_columns_exist_on_tenantable_tables(): void
    {
        foreach ([
            'agent_definitions',
            'workflow_definitions',
            'knowledge_bases',
            'tool_definitions',
            'mcp_servers',
            'mcp_endpoints',
            'variables',
            'threads',
            'runs',
            'traces',
        ] as $table) {
            $this->assertTrue(Schema::hasColumn(StudioTables::name($table), 'tenant_id'), $table);
            $this->assertTrue(Schema::hasColumn(StudioTables::name($table), 'tenant_scope'), $table);
        }
    }

    public function test_disabled_tenancy_does_not_stamp_tenant_id(): void
    {
        $agent = $this->makeAgent('plain-agent');

        $this->assertNull($agent->tenant_id);
        $this->assertSame('', $agent->tenant_scope);
    }

    public function test_tenant_cannot_see_another_tenants_agent(): void
    {
        $this->enableTenancy('tenant-a');
        $agentA = $this->makeAgent('support-bot');

        $this->enableTenancy('tenant-b');
        $this->assertNull(AgentDefinition::query()->find($agentA->id));
        $this->assertNull(AgentDefinition::findBySlug('support-bot'));

        $found = StudioTenancy::withoutScope(fn () => AgentDefinition::query()->find($agentA->id));
        $this->assertNotNull($found);
        $this->assertSame('tenant-a', $found->tenant_id);
    }

    public function test_tenant_slug_overrides_global(): void
    {
        $global = StudioTenancy::central(fn () => $this->makeAgent('support-bot', 'Global Bot'));

        $this->enableTenancy('tenant-a');
        $tenant = $this->makeAgent('support-bot', 'Acme Bot');

        $resolved = AgentDefinition::findBySlug('support-bot');
        $this->assertNotNull($resolved);
        $this->assertSame($tenant->id, $resolved->id);
        $this->assertSame('Acme Bot', $resolved->name);

        $this->enableTenancy('tenant-b');
        $resolvedB = AgentDefinition::findBySlug('support-bot');
        $this->assertNotNull($resolvedB);
        $this->assertSame($global->id, $resolvedB->id);
        $this->assertSame('Global Bot', $resolvedB->name);
    }

    public function test_http_aborts_403_when_tenancy_enabled_without_tenant(): void
    {
        $this->withoutMiddleware(EnsureNeuronAIStudioAuthorized::class);
        $this->enableTenancy(null);

        $this->get(route('neuronai-studio.agents.create'))->assertForbidden();
    }

    public function test_http_allows_studio_when_tenant_resolved(): void
    {
        $this->withoutMiddleware(EnsureNeuronAIStudioAuthorized::class);
        $this->enableTenancy('tenant-a');

        $this->get(route('neuronai-studio.agents.create'))->assertOk();
    }

    public function test_creating_without_tenant_throws(): void
    {
        $this->enableTenancy(null);

        $this->expectException(TenantRequiredException::class);
        $this->makeAgent('no-context');
    }

    public function test_variable_name_override_per_tenant(): void
    {
        StudioTenancy::central(function () {
            Variable::create([
                'name' => 'OPENAI_KEY',
                'type' => Variable::TYPE_GENERIC,
                'value' => 'global-key',
            ]);
        });

        $this->enableTenancy('tenant-a');
        Variable::create([
            'name' => 'OPENAI_KEY',
            'type' => Variable::TYPE_GENERIC,
            'value' => 'acme-key',
        ]);

        $this->assertSame('acme-key', app(VariableRepository::class)->resolveValue('OPENAI_KEY'));

        $this->enableTenancy('tenant-b');
        $this->assertSame('global-key', app(VariableRepository::class)->resolveValue('OPENAI_KEY'));
    }

    public function test_database_driver_does_not_scope_tenant_id(): void
    {
        $this->enableTenancy('tenant-a', driver: 'database');

        $a = $this->makeAgent('one');
        $a->forceFill(['tenant_id' => 'tenant-a'])->save();

        $this->enableTenancy('tenant-b', driver: 'database');
        $b = $this->makeAgent('two');
        $b->forceFill(['tenant_id' => 'tenant-b'])->save();

        $this->assertSame(2, AgentDefinition::query()->count());
    }

    public function test_run_workflow_job_restores_tenant_on_worker(): void
    {
        $this->enableTenancy('tenant-a');

        $workflow = WorkflowDefinition::create([
            'name' => 'Tenant Job Flow',
            'slug' => 'tenant-job-flow',
            'graph' => [
                'version' => 1,
                'nodes' => [
                    ['id' => 'start_1', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
                    ['id' => 'set_1', 'type' => 'set_state', 'position' => ['x' => 200, 'y' => 0], 'data' => ['key' => 'greeting', 'value' => 'Hello']],
                    ['id' => 'stop_1', 'type' => 'stop', 'position' => ['x' => 400, 'y' => 0], 'data' => []],
                ],
                'edges' => [
                    ['id' => 'e1', 'source' => 'start_1', 'target' => 'set_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                    ['id' => 'e2', 'source' => 'set_1', 'target' => 'stop_1', 'sourceHandle' => 'default', 'targetHandle' => 'default'],
                ],
            ],
        ]);

        $thread = StudioThread::create([
            'id' => (string) Str::uuid(),
            'entity_type' => WorkflowDefinition::class,
            'entity_id' => $workflow->id,
        ]);

        $run = StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => $thread->id,
            'status' => 'queued',
            'input' => ['input' => 'test'],
        ]);

        $this->assertSame('tenant-a', $run->tenant_id);

        $this->enableTenancy(null);

        $job = new RunWorkflowJob($run->id, $workflow->id, ['input' => 'test']);
        $job->handle(app(\DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner::class));

        $this->enableTenancy('tenant-a');
        $this->assertSame('completed', StudioRun::query()->find($run->id)?->status);
    }

    public function test_vector_store_namespacing_prefixes_tenant_kb_only(): void
    {
        $this->enableTenancy('tenant-a');
        $tenantKb = KnowledgeBase::create([
            'name' => 'Tenant KB',
            'slug' => 'docs',
            'vector_store_driver' => 'file',
        ]);

        $globalKb = StudioTenancy::central(fn () => KnowledgeBase::create([
            'name' => 'Global KB',
            'slug' => 'docs',
            'vector_store_driver' => 'file',
        ]));

        $factory = new VectorStoreFactory;

        $this->assertSame('tenant-a__docs', $factory->namespaced($tenantKb, 'docs'));
        $this->assertSame('docs', $factory->namespaced($globalKb, 'docs'));
        $this->assertStringContainsString('tenant-a__docs.store', $factory->fileStorePath($tenantKb));
    }

    protected function enableTenancy(?string $tenantId, string $driver = 'shared'): void
    {
        config([
            'neuronai-studio.tenancy.enabled' => true,
            'neuronai-studio.tenancy.driver' => $driver,
            'neuronai-studio.tenancy.resolver' => MutableTenantResolver::class,
        ]);
        MutableTenantResolver::$id = $tenantId;
        $this->app->forgetInstance(TenantResolver::class);
        StudioTenancy::reset();
    }

    protected function makeAgent(string $slug, string $name = 'Test Agent'): AgentDefinition
    {
        return AgentDefinition::create([
            'name' => $name,
            'slug' => $slug,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Test',
        ]);
    }
}
