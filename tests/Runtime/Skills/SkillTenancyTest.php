<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Runtime\Skills;

use DigitalElvis\NeuronAIStudio\Models\SkillDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\Skills\SkillRepository;
use DigitalElvis\NeuronAIStudio\Tenancy\StudioTenancy;
use DigitalElvis\NeuronAIStudio\Tenancy\TenantResolver;
use DigitalElvis\NeuronAIStudio\Tests\Support\MutableTenantResolver;
use DigitalElvis\NeuronAIStudio\Tests\TestCase;

class SkillTenancyTest extends TestCase
{
    protected function tearDown(): void
    {
        MutableTenantResolver::$id = null;
        StudioTenancy::reset();

        parent::tearDown();
    }

    public function test_tenant_cannot_load_another_tenants_skill_by_ref(): void
    {
        $this->enableTenancy('tenant-a');

        $skill = SkillDefinition::create([
            'slug' => 'tenant-a-skill',
            'description' => 'Private skill.',
            'body' => 'Tenant A only.',
        ]);

        $this->enableTenancy('tenant-b');

        $this->assertNull(app(SkillRepository::class)->findByRef($skill->bindingRef()));
    }

    protected function enableTenancy(?string $tenantId): void
    {
        config([
            'neuronai-studio.tenancy.enabled' => true,
            'neuronai-studio.tenancy.driver' => 'shared',
            'neuronai-studio.tenancy.resolver' => MutableTenantResolver::class,
        ]);
        MutableTenantResolver::$id = $tenantId;
        $this->app->forgetInstance(TenantResolver::class);
        StudioTenancy::reset();
    }
}
