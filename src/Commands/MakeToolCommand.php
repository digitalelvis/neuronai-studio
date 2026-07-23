<?php

namespace DigitalElvis\NeuronAIStudio\Commands;

use DigitalElvis\NeuronAIStudio\Codegen\CodegenDisabledException;
use DigitalElvis\NeuronAIStudio\Codegen\CodegenGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeToolCommand extends Command
{
    protected $signature = 'neuronai-studio:make-tool {name : The tool class name}';

    protected $description = 'Create a new Neuron AI Tool class';

    public function handle(): int
    {
        try {
            CodegenGuard::ensureExport();
        } catch (CodegenDisabledException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $name = Str::studly($this->argument('name'));

        if (! str_ends_with($name, 'Tool')) {
            $name .= 'Tool';
        }

        $namespace = config('neuronai-studio.export_namespace', 'App\\Neuron').'\\Tools';
        $path = config('neuronai-studio.export_path', app_path('Neuron')).'/Tools';
        $file = $path.'/'.$name.'.php';

        if (File::exists($file)) {
            $this->error("Tool [{$name}] already exists.");

            return self::FAILURE;
        }

        File::ensureDirectoryExists($path);

        $stub = str_replace(
            ['{{ namespace }}', '{{ className }}', '{{ toolName }}', '{{ description }}', '{{ properties }}', '{{ invokeParams }}', '{{ invokeBody }}'],
            [
                $namespace,
                $name,
                Str::snake(str_replace('Tool', '', $name)),
                'Describe what this tool does.',
                "            new ToolProperty(
                name: 'example',
                type: PropertyType::STRING,
                description: 'Example argument',
                required: true,
            ),",
                'string $example',
                "        return 'Result for: '.\$example;",
            ],
            file_get_contents(__DIR__.'/../Codegen/Stubs/tool.stub')
        );

        File::put($file, $stub);

        $this->info("Tool created: {$file}");

        return self::SUCCESS;
    }
}
