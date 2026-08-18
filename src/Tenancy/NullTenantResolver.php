<?php

namespace DigitalElvis\NeuronAIStudio\Tenancy;

final class NullTenantResolver implements TenantResolver
{
    public function id(): ?string
    {
        return null;
    }
}
