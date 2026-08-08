<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Runtime;

use Carbon\Carbon;
use DigitalElvis\NeuronAIStudio\Runtime\StudioDatetimeContext;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;

class StudioDatetimeContextTest extends TestCase
{
    public function test_defaults_use_app_timezone_and_locale(): void
    {
        config(['app.timezone' => 'America/Sao_Paulo', 'app.locale' => 'pt_BR']);

        $defaults = StudioDatetimeContext::defaults();

        $this->assertSame('America/Sao_Paulo', $defaults[StudioDatetimeContext::KEY_TIMEZONE]);
        $this->assertSame('pt_BR', $defaults[StudioDatetimeContext::KEY_LOCALE]);
        $this->assertNotSame('', $defaults[StudioDatetimeContext::KEY_NOW]);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            $defaults[StudioDatetimeContext::KEY_NOW],
        );
    }

    public function test_invalid_timezone_falls_back_to_app(): void
    {
        config(['app.timezone' => 'UTC', 'app.locale' => 'en']);

        $this->assertSame('UTC', StudioDatetimeContext::resolveTimezone('Not/A_Zone'));
        $this->assertSame('America/New_York', StudioDatetimeContext::resolveTimezone('America/New_York'));
    }

    public function test_empty_locale_falls_back_to_app(): void
    {
        config(['app.locale' => 'en']);

        $this->assertSame('en', StudioDatetimeContext::resolveLocale(null));
        $this->assertSame('en', StudioDatetimeContext::resolveLocale('  '));
        $this->assertSame('pt_BR', StudioDatetimeContext::resolveLocale('pt_BR'));
    }

    public function test_for_state_preserves_timezone_and_locale_overrides(): void
    {
        config(['app.timezone' => 'UTC', 'app.locale' => 'en']);

        $seeded = StudioDatetimeContext::forState([
            StudioDatetimeContext::KEY_TIMEZONE => 'Europe/Lisbon',
            StudioDatetimeContext::KEY_LOCALE => 'pt_PT',
            StudioDatetimeContext::KEY_NOW => '2000-01-01T00:00:00+00:00',
        ]);

        $this->assertSame('Europe/Lisbon', $seeded[StudioDatetimeContext::KEY_TIMEZONE]);
        $this->assertSame('pt_PT', $seeded[StudioDatetimeContext::KEY_LOCALE]);
        $this->assertNotSame('2000-01-01T00:00:00+00:00', $seeded[StudioDatetimeContext::KEY_NOW]);
    }

    public function test_now_iso_uses_timezone_offset(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-07 15:00:00', 'UTC'));

        $iso = StudioDatetimeContext::nowIso('America/Sao_Paulo');

        $this->assertStringContainsString('2026-08-07T12:00:00', $iso);
        $this->assertStringContainsString('-03:00', $iso);

        Carbon::setTestNow();
    }
}
