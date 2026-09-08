<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Skills;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class SkillScriptSandbox
{
    public function __construct(
        protected SkillPathPolicy $pathPolicy,
    ) {}

    /**
     * @param  list<string>  $args
     * @return array{exit_code: int, stdout: string, stderr: string}
     */
    public function run(SkillCatalogEntry $entry, string $scriptPath, array $args = []): array
    {
        if (! (bool) config('neuronai-studio.skills.execution.enabled', false)) {
            return [
                'exit_code' => 1,
                'stdout' => '',
                'stderr' => 'Skill script execution is disabled. Set neuronai-studio.skills.execution.enabled=true.',
            ];
        }

        $scriptPath = $this->pathPolicy->normalize($scriptPath);

        if (! $this->pathPolicy->isExecutable($scriptPath)) {
            return [
                'exit_code' => 1,
                'stdout' => '',
                'stderr' => "Error: script path [{$scriptPath}] is not executable under scripts/.",
            ];
        }

        $files = $entry->definition->files();

        if (! array_key_exists($scriptPath, $files)) {
            return [
                'exit_code' => 1,
                'stdout' => '',
                'stderr' => "Error: script [{$scriptPath}] not found in skill [{$entry->name}].",
            ];
        }

        foreach ($args as $arg) {
            if (! is_string($arg) || preg_match('/[|><;`&$]/', $arg)) {
                return [
                    'exit_code' => 1,
                    'stdout' => '',
                    'stderr' => 'Error: script arguments contain forbidden shell metacharacters.',
                ];
            }
        }

        $workspace = storage_path(
            'app/neuronai-studio/skills/runs/'.Str::ulid()
        );
        File::ensureDirectoryExists($workspace);

        try {
            $this->materialize($entry, $workspace);

            $absoluteScript = $workspace.'/'.$scriptPath;
            $extension = strtolower(pathinfo($scriptPath, PATHINFO_EXTENSION));
            $interpreters = config('neuronai-studio.skills.execution.interpreters', []);
            $interpreter = $interpreters[$extension] ?? null;

            if (! is_string($interpreter) || $interpreter === '') {
                return [
                    'exit_code' => 1,
                    'stdout' => '',
                    'stderr' => "Error: no interpreter configured for .{$extension}",
                ];
            }

            @chmod($absoluteScript, 0755);

            $command = array_merge([$interpreter, $absoluteScript], array_values($args));
            $timeout = (int) config('neuronai-studio.skills.execution.timeout_seconds', 30);
            $maxOutput = (int) config('neuronai-studio.skills.execution.max_output_bytes', 65_536);

            $result = Process::path($workspace)
                ->timeout($timeout)
                ->env([
                    'PATH' => getenv('PATH') ?: '/usr/bin:/bin:/usr/local/bin',
                    'HOME' => $workspace,
                    'LANG' => 'C.UTF-8',
                ])
                ->run($command);

            return [
                'exit_code' => $result->exitCode() ?? 1,
                'stdout' => $this->truncate((string) $result->output(), $maxOutput),
                'stderr' => $this->truncate((string) $result->errorOutput(), $maxOutput),
            ];
        } finally {
            if (is_dir($workspace)) {
                File::deleteDirectory($workspace);
            }
        }
    }

    protected function materialize(SkillCatalogEntry $entry, string $workspace): void
    {
        $skillMd = $entry->definition->skillMarkdown();
        File::put($workspace.'/SKILL.md', $skillMd);

        foreach ($entry->definition->files() as $path => $contents) {
            $target = $workspace.'/'.$path;
            File::ensureDirectoryExists(dirname($target));

            if (str_starts_with($contents, 'base64:')) {
                File::put($target, base64_decode(substr($contents, 7), true) ?: '');
            } else {
                File::put($target, $contents);
            }
        }
    }

    protected function truncate(string $value, int $max): string
    {
        if (strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max)."\n…[truncated]";
    }
}
