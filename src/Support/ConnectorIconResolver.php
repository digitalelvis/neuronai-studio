<?php

namespace DigitalElvis\NeuronAIStudio\Support;

use Illuminate\Support\Facades\Storage;

class ConnectorIconResolver
{
    public function resolve(?string $icon): ?string
    {
        if ($icon === null || trim($icon) === '') {
            return null;
        }

        $icon = trim($icon);

        if (str_starts_with($icon, 'http://') || str_starts_with($icon, 'https://') || str_starts_with($icon, 'data:')) {
            return $icon;
        }

        if (is_readable($icon)) {
            return $this->fileToDataUri($icon);
        }

        $publicDisk = config('neuronai-studio.connectors.icon_disk', 'public');

        if (Storage::disk($publicDisk)->exists($icon)) {
            return Storage::disk($publicDisk)->url($icon);
        }

        if (str_starts_with($icon, '/')) {
            return $icon;
        }

        return $icon;
    }

    /** @return list<string> */
    public function pluginIconCandidates(string $pluginPath): array
    {
        $base = rtrim($pluginPath, '/\\');

        return [
            $base.'/.claude-plugin/icon.png',
            $base.'/.claude-plugin/logo.png',
            $base.'/icon.png',
            $base.'/logo.png',
        ];
    }

    public function resolvePluginPath(?string $pluginPath, ?string $configuredIcon = null): ?string
    {
        if ($configuredIcon !== null && $configuredIcon !== '') {
            $resolved = $this->resolve($configuredIcon);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        if ($pluginPath === null || $pluginPath === '') {
            return null;
        }

        foreach ($this->pluginIconCandidates($pluginPath) as $candidate) {
            if (is_readable($candidate)) {
                return $this->fileToDataUri($candidate);
            }
        }

        return null;
    }

    protected function fileToDataUri(string $path): string
    {
        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
    }
}
