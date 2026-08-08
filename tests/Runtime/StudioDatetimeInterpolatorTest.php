<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Runtime;

use Carbon\Carbon;
use DigitalElvis\NeuronAIStudio\Runtime\StateTemplateInterpolator;
use DigitalElvis\NeuronAIStudio\Runtime\StudioDatetimeContext;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use NeuronAI\Workflow\WorkflowState;

class StudioDatetimeInterpolatorTest extends TestCase
{
    public function test_interpolate_uses_fresh_now_with_state_timezone(): void
    {
        config(['app.timezone' => 'UTC', 'app.locale' => 'en']);
        Carbon::setTestNow(Carbon::parse('2026-08-07 18:30:00', 'UTC'));

        $state = new WorkflowState([
            StudioDatetimeContext::KEY_TIMEZONE => 'America/Sao_Paulo',
            StudioDatetimeContext::KEY_LOCALE => 'pt_BR',
            StudioDatetimeContext::KEY_NOW => 'stale-value',
        ]);

        $out = StateTemplateInterpolator::interpolate(
            'Now={{__studio_now}} TZ={{__studio_timezone}} Locale={{__studio_locale}}',
            $state,
        );

        $this->assertStringContainsString('Now=2026-08-07T15:30:00-03:00', $out);
        $this->assertStringContainsString('TZ=America/Sao_Paulo', $out);
        $this->assertStringContainsString('Locale=pt_BR', $out);
        $this->assertStringNotContainsString('stale-value', $out);

        Carbon::setTestNow();
    }

    public function test_standalone_datetime_placeholders_resolve_from_app_defaults(): void
    {
        config(['app.timezone' => 'UTC', 'app.locale' => 'en']);
        Carbon::setTestNow(Carbon::parse('2026-01-15 12:00:00', 'UTC'));

        $out = StateTemplateInterpolator::interpolateStudioDatetimePlaceholders(
            't={{__studio_now}} z={{__studio_timezone}} l={{__studio_locale}}',
        );

        $this->assertSame('t=2026-01-15T12:00:00+00:00 z=UTC l=en', $out);
        Carbon::setTestNow();
    }
}
