<?php

namespace DigitalElvis\NeuronAIStudio\Tests\Fixtures\Output;

use NeuronAI\StructuredOutput\SchemaProperty;

abstract class AbstractLeadProfile
{
    #[SchemaProperty(description: 'Lead email address', required: true)]
    public string $email;
}
