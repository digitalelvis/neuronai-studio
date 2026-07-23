<?php

namespace DigitalElvis\NeuronAIStudio\Commands;

use DigitalElvis\NeuronAIStudio\Codegen\AgentExporter;
use DigitalElvis\NeuronAIStudio\Codegen\CodegenDisabledException;
use DigitalElvis\NeuronAIStudio\Codegen\CodegenGuard;
use DigitalElvis\NeuronAIStudio\Codegen\WorkflowExporter;
use DigitalElvis\NeuronAIStudio\Models\AgentDefinition;
use DigitalElvis\NeuronAIStudio\Models\WorkflowDefinition;
use Illuminate\Console\Command;

class ExportCommand extends Command
{
    protected $signature = 'neuronai-studio:export {type : agent or workflow} {id : The record ID}';

    protected $description = 'Export an agent or workflow definition to PHP classes';

    public function handle(AgentExporter $agentExporter, WorkflowExporter $workflowExporter): int
    {
        try {
            CodegenGuard::ensureExport();
        } catch (CodegenDisabledException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $type = $this->argument('type');
        $id = (int) $this->argument('id');

        $files = match ($type) {
            'agent' => $agentExporter->export(AgentDefinition::findOrFail($id)),
            'workflow' => $workflowExporter->export(WorkflowDefinition::findOrFail($id)),
            default => null,
        };

        if ($files === null) {
            $this->error('Type must be "agent" or "workflow".');

            return self::FAILURE;
        }

        foreach ($files as $file) {
            $this->line("Exported: {$file}");
        }

        return self::SUCCESS;
    }
}
