<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Support;

use DigitalElvis\NeuronAIStudio\Tenancy\TenantResolver;

final class MutableTenantResolver implements TenantResolver
{
    public static ?string $id = null;

    public function id(): ?string
    {
        return self::$id;
    }
}
