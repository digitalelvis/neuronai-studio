<?php

namespace DigitalElvis\NeuronAIStudio;

use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunner;
use DigitalElvis\NeuronAIStudio\Runtime\AgentRunResult;
use DigitalElvis\NeuronAIStudio\Runtime\WorkflowRunner;
use DigitalElvis\NeuronAIStudio\Support\ThreadOwner;
use Generator;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Thin invoke builder: stamps owner / thread onto runner payloads without fluent runners.
 */
final class StudioInvoke
{
    private AgentDefinition|WorkflowDefinition $target;

    private ?ThreadOwner $owner = null;

    private ?string $threadId = null;

    private function __construct(AgentDefinition|WorkflowDefinition $target)
    {
        $this->target = $target;
    }

    public static function workflow(WorkflowDefinition $workflow): self
    {
        return new self($workflow);
    }

    public static function agent(AgentDefinition $agent): self
    {
        return new self($agent);
    }

    public function forOwner(Model $owner): self
    {
        $clone = clone $this;
        $clone->owner = ThreadOwner::fromModel($owner);

        return $clone;
    }

    public function onThread(string $threadId): self
    {
        if ($threadId === '') {
            throw new InvalidArgumentException('threadId must not be empty.');
        }

        $clone = clone $this;
        $clone->threadId = $threadId;

        return $clone;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function run(array $input = [], ?callable $emitter = null, ?StudioRun $parentRun = null): StudioRun|AgentRunResult
    {
        $payload = $this->mergePayload($input);

        if ($this->target instanceof WorkflowDefinition) {
            return app(WorkflowRunner::class)->run($this->target, $payload, $emitter, $parentRun);
        }

        $message = (string) ($payload['message'] ?? $payload['input'] ?? '');

        return app(AgentRunner::class)->run(
            $this->target,
            $message,
            (bool) ($payload['fake'] ?? false),
            $payload,
        );
    }

    /**
     * Stream an agent (playground / integrate style payload).
     *
     * @param  array<string, mixed>  $payload
     */
    public function stream(array $payload = []): Generator
    {
        if (! $this->target instanceof AgentDefinition) {
            throw new InvalidArgumentException('stream() is only available for agents. Use run() for workflows.');
        }

        return app(AgentRunner::class)->stream($this->target, $this->mergePayload($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function mergePayload(array $payload): array
    {
        if ($this->threadId !== null) {
            $payload['thread_id'] = $this->threadId;
        }

        if ($this->owner !== null) {
            $payload = array_merge($payload, $this->owner->toInput());
        }

        return $payload;
    }
}
