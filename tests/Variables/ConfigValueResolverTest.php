<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Variables;

use DigitalElvis\NeuronAIStudio\Exceptions\VariableResolutionException;
use DigitalElvis\NeuronAIStudio\Models\Variable;
use DigitalElvis\NeuronAIStudio\Runtime\ConfigValueResolver;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;

class ConfigValueResolverTest extends TestCase
{
    public function test_resolves_var_reference(): void
    {
        Variable::create([
            'name' => 'MY_TOKEN',
            'type' => Variable::TYPE_CREDENTIAL,
            'value' => 'tok-123',
        ]);

        $resolver = app(ConfigValueResolver::class);
        $this->assertSame('tok-123', $resolver->resolve('var:MY_TOKEN'));
    }

    public function test_missing_var_throws(): void
    {
        $this->expectException(VariableResolutionException::class);
        app(ConfigValueResolver::class)->resolve('var:DOES_NOT_EXIST');
    }

    public function test_resolves_env_prefix(): void
    {
        putenv('STUDIO_TEST_ENV_VAR=from-env');
        $_ENV['STUDIO_TEST_ENV_VAR'] = 'from-env';

        $this->assertSame('from-env', app(ConfigValueResolver::class)->resolve('env:STUDIO_TEST_ENV_VAR'));
    }

    public function test_nested_array_walk(): void
    {
        Variable::create([
            'name' => 'NESTED',
            'type' => Variable::TYPE_GENERIC,
            'value' => 'nested-val',
        ]);

        $resolved = app(ConfigValueResolver::class)->resolve([
            'a' => 'literal',
            'b' => ['c' => 'var:NESTED'],
        ]);

        $this->assertSame('literal', $resolved['a']);
        $this->assertSame('nested-val', $resolved['b']['c']);
    }

    public function test_resolve_env_name_or_var(): void
    {
        Variable::create([
            'name' => 'MCP_TOKEN',
            'type' => Variable::TYPE_CREDENTIAL,
            'value' => 'mcp-secret',
        ]);

        putenv('LEGACY_TOKEN=legacy-secret');
        $_ENV['LEGACY_TOKEN'] = 'legacy-secret';

        $resolver = app(ConfigValueResolver::class);
        $this->assertSame('mcp-secret', $resolver->resolveEnvNameOrVar('var:MCP_TOKEN'));
        $this->assertSame('legacy-secret', $resolver->resolveEnvNameOrVar('LEGACY_TOKEN'));
    }
}
