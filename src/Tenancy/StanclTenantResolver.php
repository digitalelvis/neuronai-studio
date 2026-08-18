<?php

namespace DigitalElvis\NeuronAIStudio\Tenancy;

/**
 * Optional resolver for hosts using stancl/tenancy. Not imported as a Composer
 * dependency — uses the `tenant()` helper when present.
 */
final class StanclTenantResolver implements TenantResolver
{
    public function id(): ?string
    {
        if (! function_exists('tenant')) {
            return null;
        }

        $tenant = tenant();

        if (! is_object($tenant) || ! method_exists($tenant, 'getTenantKey')) {
            return null;
        }

        $key = $tenant->getTenantKey();

        if ($key === null || $key === '') {
            return null;
        }

        return (string) $key;
    }
}
