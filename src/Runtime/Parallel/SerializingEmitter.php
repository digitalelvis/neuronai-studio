<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Parallel;

/**
 * Serializes concurrent emitStep calls so SSE JSON lines never interleave mid-payload.
 */
class SerializingEmitter
{
    /** @var callable|null */
    protected $inner;

    public function __construct(?callable $inner)
    {
        $this->inner = $inner;
    }

    /** @param  array<string, mixed>  $data */
    public function __invoke(string $event, array $data = []): void
    {
        if ($this->inner === null) {
            return;
        }

        ($this->inner)($event, $data);
    }

    public function wrapStateEmitter(object $state): void
    {
        if (! property_exists($state, 'stepEmitter')) {
            return;
        }

        $inner = $state->stepEmitter;
        if ($inner === null) {
            return;
        }

        $state->stepEmitter = $this;
        $this->inner = $inner instanceof self ? $inner->inner : $inner;
    }
}
