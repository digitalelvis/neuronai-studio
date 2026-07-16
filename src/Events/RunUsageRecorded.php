<?php

namespace DigitalElvis\NeuronAIStudio\Events;

use DigitalElvis\NeuronAIStudio\Models\StudioRun;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RunUsageRecorded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public StudioRun $run,
        public int $promptTokens,
        public int $completionTokens,
        public int $totalTokens,
        public string $estimatedCost,
        public string $currency,
        public ?string $entityType,
        public int|string|null $entityId,
        public ?string $parentRunId,
    ) {}

    public static function fromRun(StudioRun $run): self
    {
        $run->loadMissing('thread');

        $entityType = match ($run->thread?->entity_type) {
            \DigitalElvis\NeuronAIStudio\Models\AgentDefinition::class => 'agent',
            \DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition::class => 'workflow',
            default => $run->thread?->entity_type,
        };

        return new self(
            run: $run,
            promptTokens: (int) $run->prompt_tokens,
            completionTokens: (int) $run->completion_tokens,
            totalTokens: (int) $run->total_tokens,
            estimatedCost: number_format((float) $run->estimated_cost, 6, '.', ''),
            currency: (string) config('neuronai-studio.usage.currency', 'USD'),
            entityType: $entityType,
            entityId: $run->thread?->entity_id,
            parentRunId: $run->parent_run_id,
        );
    }
}
