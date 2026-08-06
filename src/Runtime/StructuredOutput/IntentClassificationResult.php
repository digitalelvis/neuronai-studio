<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\StructuredOutput;

use NeuronAI\StructuredOutput\SchemaProperty;
use NeuronAI\StructuredOutput\Validation\Rules\NotBlank;

/**
 * Structured output for Intent Classifier nodes.
 * Allowed intent ids are constrained via instructions + post-validation.
 */
class IntentClassificationResult
{
    #[SchemaProperty(
        description: 'The intent id that best matches the user message. Must be exactly one of the allowed intent ids listed in the system instructions.',
        required: true,
    )]
    #[NotBlank]
    public string $intent_id;
}
