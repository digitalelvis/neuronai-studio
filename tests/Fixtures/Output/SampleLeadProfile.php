<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Fixtures\Output;

use NeuronAI\StructuredOutput\SchemaProperty;

class SampleLeadProfile
{
    #[SchemaProperty(description: 'Lead email address', required: true)]
    public string $email;

    #[SchemaProperty(description: 'Lead tier', required: false)]
    public ?string $tier = null;
}
