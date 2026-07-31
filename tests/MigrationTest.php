<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioTrace;
use DigitalElvis\NeuronAIStudio\Models\StudioTraceSpan;
use DigitalElvis\NeuronAIStudio\Support\StudioTables;
use Illuminate\Support\Str;

class MigrationTest extends TestCase
{
    public function test_migrations_create_tables(): void
    {
        $this->assertTrue(\Schema::hasTable(StudioTables::name('agent_definitions')));
        $this->assertTrue(\Schema::hasTable(StudioTables::name('workflow_definitions')));
        $this->assertTrue(\Schema::hasTable(StudioTables::name('threads')));
        $this->assertTrue(\Schema::hasTable(StudioTables::name('runs')));
        $this->assertTrue(\Schema::hasTable(StudioTables::name('traces')));
        $this->assertTrue(\Schema::hasTable(StudioTables::name('trace_spans')));
        $this->assertTrue(\Schema::hasTable(StudioTables::name('mcp_servers')));
        $this->assertTrue(\Schema::hasTable(StudioTables::name('agent_mcp_server')));
        $this->assertTrue(\Schema::hasTable(StudioTables::name('mcp_endpoints')));
        $this->assertTrue(\Schema::hasTable(StudioTables::name('mcp_endpoint_bindings')));

        // MySQL identifier limit is 64 chars; keep these names short explicitly.
        $this->assertTrue(
            \Schema::hasIndex(StudioTables::name('agent_mcp_server'), 'ns_agent_mcp_server_unique')
        );
        $this->assertTrue(
            \Schema::hasIndex(StudioTables::name('mcp_endpoint_bindings'), 'ns_mcp_ep_bindings_enabled_idx')
        );
        $this->assertTrue(
            \Schema::hasIndex(StudioTables::name('mcp_endpoint_bindings'), 'ns_mcp_ep_bindings_kind_ref_uq')
        );
    }

    public function test_all_package_tables_use_prefix(): void
    {
        foreach (StudioTables::TABLES as $table) {
            $this->assertTrue(
                \Schema::hasTable(StudioTables::name($table)),
                "Expected prefixed table for [{$table}]"
            );
            $this->assertFalse(
                \Schema::hasTable($table),
                "Unprefixed table [{$table}] must not exist"
            );
        }

        $this->assertCount(count(StudioTables::TABLES), StudioTables::all());
        $this->assertSame(
            array_map(fn (string $table) => StudioTables::name($table), StudioTables::TABLES),
            StudioTables::all()
        );
    }

    public function test_models_can_persist_records(): void
    {
        $agent = AgentDefinition::create([
            'name' => 'Test Agent',
            'slug' => 'test-agent',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'instructions' => 'Test',
        ]);

        $workflow = WorkflowDefinition::create([
            'name' => 'Test Workflow',
            'slug' => 'test-workflow',
            'graph' => WorkflowDefinition::defaultGraph(),
        ]);

        $thread = StudioThread::create([
            'id' => (string) Str::uuid(),
            'entity_type' => WorkflowDefinition::class,
            'entity_id' => $workflow->id,
        ]);

        $run = StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => $thread->id,
            'status' => 'completed',
        ]);

        $trace = StudioTrace::create([
            'id' => (string) Str::uuid(),
            'run_id' => $run->id,
        ]);

        $span = StudioTraceSpan::create([
            'id' => (string) Str::uuid(),
            'trace_id' => $trace->id,
            'name' => 'start_node',
            'type' => 'node',
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas(StudioTables::name('agent_definitions'), ['id' => $agent->id]);
        $this->assertDatabaseHas(StudioTables::name('workflow_definitions'), ['id' => $workflow->id]);
        $this->assertDatabaseHas(StudioTables::name('threads'), ['id' => $thread->id]);
        $this->assertDatabaseHas(StudioTables::name('runs'), ['id' => $run->id]);
        $this->assertDatabaseHas(StudioTables::name('traces'), ['id' => $trace->id]);
        $this->assertDatabaseHas(StudioTables::name('trace_spans'), ['id' => $span->id]);
    }

    public function test_usage_cost_columns_exist_on_runs_and_spans(): void
    {
        $runs = StudioTables::name('runs');
        $spans = StudioTables::name('trace_spans');

        $this->assertTrue(\Schema::hasColumn($runs, 'estimated_cost'));
        $this->assertTrue(\Schema::hasColumn($runs, 'parent_run_id'));
        $this->assertTrue(\Schema::hasIndex($runs, ['started_at']));
        $this->assertTrue(\Schema::hasIndex($runs, ['parent_run_id']));

        $this->assertTrue(\Schema::hasColumn($spans, 'provider'));
        $this->assertTrue(\Schema::hasColumn($spans, 'model'));
        $this->assertTrue(\Schema::hasColumn($spans, 'estimated_cost'));

        $threadId = (string) Str::uuid();
        \DB::table(StudioTables::name('threads'))->insert([
            'id' => $threadId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $parentId = (string) Str::uuid();
        \DB::table($runs)->insert([
            'id' => $parentId,
            'thread_id' => $threadId,
            'status' => 'running',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $childId = (string) Str::uuid();
        \DB::table($runs)->insert([
            'id' => $childId,
            'thread_id' => $threadId,
            'parent_run_id' => $parentId,
            'status' => 'completed',
            'estimated_cost' => 1.25,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas($runs, [
            'id' => $childId,
            'parent_run_id' => $parentId,
        ]);
        $this->assertSame('0', (string) \DB::table($runs)->where('id', $parentId)->value('estimated_cost'));

        $traceId = (string) Str::uuid();
        \DB::table(StudioTables::name('traces'))->insert([
            'id' => $traceId,
            'run_id' => $childId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $spanId = (string) Str::uuid();
        \DB::table($spans)->insert([
            'id' => $spanId,
            'trace_id' => $traceId,
            'name' => 'llm',
            'type' => 'llm',
            'status' => 'completed',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'estimated_cost' => 0.0015,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas($spans, [
            'id' => $spanId,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
        ]);
    }

    public function test_run_parent_children_relations_and_usage_fillable(): void
    {
        $thread = StudioThread::create([
            'id' => (string) Str::uuid(),
        ]);

        $parent = StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => $thread->id,
            'status' => 'running',
            'estimated_cost' => '0.100000',
        ]);

        $child = StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => $thread->id,
            'parent_run_id' => $parent->id,
            'status' => 'completed',
            'estimated_cost' => '0.050000',
        ]);

        $this->assertTrue($child->parent->is($parent));
        $this->assertTrue($parent->children->contains(fn (StudioRun $run) => $run->is($child)));
        $this->assertSame('0.100000', $parent->fresh()->estimated_cost);
        $this->assertSame('0.050000', $child->fresh()->estimated_cost);

        $trace = StudioTrace::create([
            'id' => (string) Str::uuid(),
            'run_id' => $child->id,
        ]);

        $span = StudioTraceSpan::create([
            'id' => (string) Str::uuid(),
            'trace_id' => $trace->id,
            'name' => 'llm',
            'type' => 'llm',
            'status' => 'completed',
            'provider' => 'anthropic',
            'model' => 'claude-sonnet-4-20250514',
            'estimated_cost' => '0.012500',
        ]);

        $span = $span->fresh();
        $this->assertSame('anthropic', $span->provider);
        $this->assertSame('claude-sonnet-4-20250514', $span->model);
        $this->assertSame('0.012500', $span->estimated_cost);
    }
}
