<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Variables;

use DigitalElvis\NeuronAIStudio\Codegen\AgentExporter;
use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\Variable;
use DigitalElvis\NeuronAIStudio\Registry\ProviderRegistry;
use DigitalElvis\NeuronAIStudio\Runtime\StateTemplateInterpolator;
use DigitalElvis\NeuronAIStudio\Support\SecretScrubber;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;
use Illuminate\Support\Facades\File;
use NeuronAI\Workflow\WorkflowState;

class ProviderOverrideAndInterpolatorTest extends TestCase
{
    public function test_provider_registry_accepts_var_override(): void
    {
        Variable::create([
            'name' => 'OPENAI_OVERRIDE',
            'type' => Variable::TYPE_CREDENTIAL,
            'value' => 'sk-from-vault',
        ]);

        config(['neuron.provider.openai' => [
            'key' => '',
            'model' => 'gpt-4o-mini',
            'parameters' => [],
        ]]);

        $provider = app(ProviderRegistry::class)->resolve('openai', 'gpt-4o-mini', [], 'var:OPENAI_OVERRIDE');
        $this->assertNotNull($provider);
    }

    public function test_interpolates_var_in_template(): void
    {
        Variable::create([
            'name' => 'BASE_URL',
            'type' => Variable::TYPE_GENERIC,
            'value' => 'https://api.example.com',
        ]);

        $out = StateTemplateInterpolator::interpolateVariablesOnly('Call {{ var.BASE_URL }}/v1');
        $this->assertSame('Call https://api.example.com/v1', $out);

        $state = new WorkflowState(['input' => 'hello']);
        $mixed = StateTemplateInterpolator::interpolate('{{input}} @ {{ var.BASE_URL }}', $state);
        $this->assertSame('hello @ https://api.example.com', $mixed);
    }

    public function test_export_keeps_var_ref(): void
    {
        $exportPath = storage_path('framework/testing/neuron-vars');
        config(['neuronai-studio.export_path' => $exportPath]);
        config(['neuronai-studio.export_namespace' => 'App\\Neuron']);
        File::deleteDirectory($exportPath);

        Variable::create([
            'name' => 'EXPORT_KEY',
            'type' => Variable::TYPE_CREDENTIAL,
            'value' => 'must-not-appear',
        ]);

        $agent = AgentDefinition::create([
            'name' => 'Vault Bot',
            'slug' => 'vault-bot',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'api_key' => 'var:EXPORT_KEY',
            'instructions' => 'Use {{ var.BASE_URL }}',
        ]);

        $files = app(AgentExporter::class)->export($agent);
        $content = file_get_contents($files[0]);

        $this->assertStringContainsString('var:EXPORT_KEY', $content);
        $this->assertStringNotContainsString('must-not-appear', $content);
        $this->assertStringContainsString('ConfigValueResolver', $content);

        File::deleteDirectory($exportPath);
    }

    public function test_secret_scrubber_masks_keys(): void
    {
        $scrubbed = SecretScrubber::scrub([
            'api_key' => 'sk-secret',
            'safe' => 'ok',
            'nested' => ['token' => 'abc'],
        ]);

        $this->assertSame('*****', $scrubbed['api_key']);
        $this->assertSame('ok', $scrubbed['safe']);
        $this->assertSame('*****', $scrubbed['nested']['token']);
    }
}
