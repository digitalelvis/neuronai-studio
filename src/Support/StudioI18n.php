<?php

namespace DigitalElvis\NeuronAIStudio\Support;

class StudioI18n
{
    /**
     * Flat message catalog for React bundles (window.__STUDIO_I18N__).
     *
     * @return array<string, string>
     */
    public static function jsMessages(?string $locale = null): array
    {
        $locale = $locale ?: app()->getLocale();
        $base = dirname(__DIR__, 2).'/resources/js/i18n';

        $path = $base.'/'.$locale.'.json';
        if (! is_file($path)) {
            $path = $base.'/en.json';
        }

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }
}
