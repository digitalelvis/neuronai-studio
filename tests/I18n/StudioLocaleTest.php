<?php

namespace DigitalElvis\NeuronAIStudio\Tests\I18n;

use DigitalElvis\NeuronAIStudio\Http\Middleware\SetStudioLocale;
use DigitalElvis\NeuronAIStudio\Registry\NodeTypeRegistry;
use DigitalElvis\NeuronAIStudio\Support\StudioLocale;
use DigitalElvis\NeuronAIStudio\Support\StudioTranslator;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use Illuminate\Http\Request;

class StudioLocaleTest extends TestCase
{
    public function test_resolve_follows_app_locale_when_config_empty(): void
    {
        config(['neuronai-studio.locale' => null]);
        app()->setLocale('pt_BR');

        $this->assertSame('pt_BR', StudioLocale::resolve());
    }

    public function test_resolve_uses_config_override_when_set(): void
    {
        config(['neuronai-studio.locale' => 'en']);
        app()->setLocale('pt_BR');

        $this->assertSame('en', StudioLocale::resolve());
    }

    public function test_middleware_applies_override_locale(): void
    {
        config(['neuronai-studio.locale' => 'pt_BR']);
        app()->setLocale('en');

        $middleware = new SetStudioLocale;
        $middleware->handle(Request::create('/neuronai-studio'), function () {
            return response('ok');
        });

        $this->assertSame('pt_BR', app()->getLocale());
        $this->assertSame('Agentes', __('neuronai-studio::ui.nav.agents'));
    }

    public function test_english_and_portuguese_catalogs_differ_for_nav(): void
    {
        app()->setLocale('en');
        $en = __('neuronai-studio::ui.nav.agents');

        app()->setLocale('pt_BR');
        $pt = __('neuronai-studio::ui.nav.agents');

        $this->assertSame('Agents', $en);
        $this->assertSame('Agentes', $pt);
        $this->assertNotSame($en, $pt);
    }

    public function test_flash_and_node_keys_resolve(): void
    {
        app()->setLocale('pt_BR');

        $this->assertSame('Agente salvo com sucesso.', __('neuronai-studio::flash.agent_saved'));
        $this->assertSame('Agente', __('neuronai-studio::nodes.agent'));
    }

    public function test_node_type_registry_translates_labels(): void
    {
        app()->setLocale('pt_BR');

        $canvas = app(NodeTypeRegistry::class)->forCanvas();

        $this->assertSame('Agente', $canvas['agent']['label'] ?? null);
        $this->assertSame('Início', $canvas['start']['label'] ?? null);
        $this->assertSame('Executar Workflow', $canvas['run_workflow']['label'] ?? null);
    }

    public function test_studio_translator_falls_back_when_key_missing(): void
    {
        $this->assertSame(
            'Fallback Label',
            StudioTranslator::get('nodes.does_not_exist_xyz', 'Fallback Label')
        );
    }

    public function test_lang_catalogs_exist_for_en_and_pt_br(): void
    {
        $root = dirname(__DIR__, 2);

        $this->assertFileExists($root.'/lang/en/ui.php');
        $this->assertFileExists($root.'/lang/pt_BR/ui.php');
        $this->assertFileExists($root.'/lang/en/flash.php');
        $this->assertFileExists($root.'/lang/pt_BR/nodes.php');
    }
}
