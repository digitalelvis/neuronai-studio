<?php

namespace DigitalElvis\NeuronAIStudio\McpServer;

use RuntimeException;

class McpProtocolException extends RuntimeException
{
    public function __construct(string $message, int $code = -32603)
    {
        parent::__construct($message, $code);
    }
}
