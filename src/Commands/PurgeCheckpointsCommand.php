<?php

namespace DigitalElvis\NeuronAIStudio\Commands;

use DigitalElvis\NeuronAIStudio\Runtime\Checkpoint\CheckpointService;
use Illuminate\Console\Command;

class PurgeCheckpointsCommand extends Command
{
    protected $signature = 'neuronai-studio:checkpoints:purge';

    protected $description = 'Delete expired workflow node checkpoints (based on checkpoints.ttl)';

    public function handle(CheckpointService $checkpoints): int
    {
        $deleted = $checkpoints->purgeExpired();

        $this->info("Purged {$deleted} expired workflow checkpoint(s).");

        return self::SUCCESS;
    }
}
