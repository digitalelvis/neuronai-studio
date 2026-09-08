<?php

namespace DigitalElvis\NeuronAIStudio\Runtime\Skills;

use DigitalElvis\NeuronAIStudio\Models\SkillDefinition;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class SkillGitHubImporter
{
    public function __construct(
        protected SkillArchiveImporter $archiveImporter,
    ) {}

    /**
     * @param  array{overwrite?: bool, categories?: ?array, category?: ?string}  $options
     */
    public function import(string $url, array $options = []): SkillDefinition
    {
        $parsed = $this->parseGitHubUrl($url);

        $tmpDir = sys_get_temp_dir().'/neuronai-gh-skill-'.uniqid('', true);
        File::ensureDirectoryExists($tmpDir);

        try {
            $this->downloadSkillTree($parsed, $tmpDir);

            $sourceMeta = $this->fetchRepoMeta($parsed['owner'], $parsed['repo']);

            return $this->archiveImporter->importFromRoot($tmpDir, [
                'overwrite' => $options['overwrite'] ?? false,
                'categories' => $options['categories'] ?? null,
                'category' => $options['category'] ?? null,
                'source' => SkillDefinition::SOURCE_GITHUB,
                'source_url' => $url,
                'source_meta' => $sourceMeta,
            ]);
        } finally {
            if (is_dir($tmpDir)) {
                File::deleteDirectory($tmpDir);
            }
        }
    }

    /**
     * @return array{owner: string, repo: string, ref: string, path: string}
     */
    public function parseGitHubUrl(string $url): array
    {
        $url = trim($url);

        if ($url === '') {
            throw new InvalidArgumentException('GitHub URL is required.');
        }

        // https://github.com/owner/repo/tree/ref/path/to/skill
        if (preg_match(
            '#^https?://github\.com/(?P<owner>[^/]+)/(?P<repo>[^/]+)/tree/(?P<ref>[^/]+)(?:/(?P<path>.*))?$#i',
            $url,
            $matches
        )) {
            return [
                'owner' => $matches['owner'],
                'repo' => preg_replace('/\.git$/', '', $matches['repo']) ?? $matches['repo'],
                'ref' => $matches['ref'],
                'path' => trim((string) ($matches['path'] ?? ''), '/'),
            ];
        }

        // https://github.com/owner/repo
        if (preg_match('#^https?://github\.com/(?P<owner>[^/]+)/(?P<repo>[^/]+)/?$#i', $url, $matches)) {
            return [
                'owner' => $matches['owner'],
                'repo' => preg_replace('/\.git$/', '', $matches['repo']) ?? $matches['repo'],
                'ref' => 'main',
                'path' => '',
            ];
        }

        throw new InvalidArgumentException('Unrecognized GitHub URL. Use a repository or tree URL.');
    }

    /**
     * @param  array{owner: string, repo: string, ref: string, path: string}  $parsed
     */
    protected function downloadSkillTree(array $parsed, string $targetDir): void
    {
        $zipUrl = sprintf(
            'https://codeload.github.com/%s/%s/zip/refs/heads/%s',
            $parsed['owner'],
            $parsed['repo'],
            $parsed['ref']
        );

        $response = Http::withHeaders($this->headers())
            ->timeout(60)
            ->get($zipUrl);

        if (! $response->successful()) {
            // Try tags/refs as commit-ish via archive API
            $zipUrl = sprintf(
                'https://api.github.com/repos/%s/%s/zipball/%s',
                $parsed['owner'],
                $parsed['repo'],
                $parsed['ref']
            );
            $response = Http::withHeaders($this->headers())
                ->timeout(60)
                ->get($zipUrl);
        }

        if (! $response->successful()) {
            throw new InvalidArgumentException(
                "Failed to download GitHub archive ({$response->status()}). Check the URL and rate limits."
            );
        }

        $zipPath = $targetDir.'.zip';
        File::put($zipPath, $response->body());

        $extractDir = $targetDir.'_extract';
        File::ensureDirectoryExists($extractDir);

        $zip = new \ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new InvalidArgumentException('Unable to open downloaded GitHub archive.');
        }

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if ($name === false) {
                    continue;
                }

                $normalized = str_replace('\\', '/', $name);
                if (str_contains($normalized, '..') || str_ends_with($normalized, '/')) {
                    continue;
                }

                $parts = explode('/', $normalized);
                array_shift($parts); // strip repo-ref root folder
                $relative = implode('/', $parts);

                if ($relative === '') {
                    continue;
                }

                if ($parsed['path'] !== '') {
                    if ($relative !== $parsed['path'] && ! str_starts_with($relative, $parsed['path'].'/')) {
                        continue;
                    }
                    $relative = ltrim(substr($relative, strlen($parsed['path'])), '/');
                }

                if ($relative === '') {
                    continue;
                }

                $dest = $targetDir.'/'.$relative;
                File::ensureDirectoryExists(dirname($dest));
                $contents = $zip->getFromIndex($i);
                if ($contents !== false) {
                    File::put($dest, $contents);
                }
            }
        } finally {
            $zip->close();
            File::delete($zipPath);
            if (is_dir($extractDir)) {
                File::deleteDirectory($extractDir);
            }
        }

        if (! is_file($targetDir.'/SKILL.md')) {
            throw new InvalidArgumentException('Downloaded path does not contain SKILL.md.');
        }
    }

    /** @return array<string, mixed>|null */
    protected function fetchRepoMeta(string $owner, string $repo): ?array
    {
        $response = Http::withHeaders($this->headers())
            ->timeout(15)
            ->get("https://api.github.com/repos/{$owner}/{$repo}");

        if (! $response->successful()) {
            return null;
        }

        $json = $response->json();

        return [
            'stars' => $json['stargazers_count'] ?? null,
            'forks' => $json['forks_count'] ?? null,
            'updated_at' => $json['updated_at'] ?? null,
            'full_name' => $json['full_name'] ?? "{$owner}/{$repo}",
        ];
    }

    /** @return array<string, string> */
    protected function headers(): array
    {
        $headers = [
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => (string) config('neuronai-studio.skills.github.user_agent', 'NeuronAI-Studio'),
        ];

        $token = config('neuronai-studio.skills.github.token');
        if (is_string($token) && $token !== '') {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        return $headers;
    }
}
