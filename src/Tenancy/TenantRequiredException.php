<?php

namespace DigitalElvis\NeuronAIStudio\Tenancy;

use RuntimeException;

class TenantRequiredException extends RuntimeException
{
    public static function forWrite(): self
    {
        return new self(
            'Studio tenancy is enabled but no tenant is in context. Wrap the write in StudioTenancy::run() or StudioTenancy::central().'
        );
    }
}
