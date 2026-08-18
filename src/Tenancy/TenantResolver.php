<?php

namespace DigitalElvis\NeuronAIStudio\Tenancy;

interface TenantResolver
{
    /**
     * Opaque tenant identifier for the current request/job, or null when none.
     */
    public function id(): ?string;
}
