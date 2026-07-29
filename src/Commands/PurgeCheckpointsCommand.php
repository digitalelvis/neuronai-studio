<?php

namespace DigitalElvis\NeuronAIStudio\Commands;

use DigitalElvis\NeuronAIStudio\Runtime\Checkpoint\CheckpointService;
use DigitalElvis\NeuronAIStudio\Support\StudioLocale;
use Illuminate\Console\Command;

class PurgeCheckpointsCommand extends Command
{
    protected $signature = 'neuronai-studio:checkpoints:purge';

    protected $description = 'Delete expired workflow node checkpoints (based on checkpoints.ttl)';

    public function handle(CheckpointService $checkpoints): int
    {
        StudioLocale::apply();

        $deleted = $checkpoints->purgeExpired();

        $this->info(__('neuronai-studio::commands.purge_checkpoints', ['count' => $deleted]));

        return self::SUCCESS;
    }
}
