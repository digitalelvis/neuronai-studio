<?php

namespace DigitalElvis\NeuronAIStudio\Tests;

use DigitalElvis\NeuronAIStudio\Http\Livewire\Dashboard;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\StudioThread;
use Illuminate\Support\Str;
use Livewire\Livewire;

class DashboardUsageTest extends TestCase
{
    public function test_dashboard_shows_thirty_day_usage_and_recent_run_columns(): void
    {
        $thread = StudioThread::create(['id' => (string) Str::uuid()]);
        StudioRun::create([
            'id' => (string) Str::uuid(),
            'thread_id' => $thread->id,
            'status' => 'completed',
            'prompt_tokens' => 800,
            'completion_tokens' => 400,
            'total_tokens' => 1200,
            'estimated_cost' => '1.250000',
            'started_at' => now()->subDay(),
            'finished_at' => now()->subDay(),
        ]);

        Livewire::test(Dashboard::class)
            ->assertSee('Tokens (30d)')
            ->assertSee('1,200')
            ->assertSee('Est. cost (30d)')
            ->assertSee('USD 1.25')
            ->assertSee('Tokens');
    }
}
