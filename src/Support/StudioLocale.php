<?php

namespace DigitalElvis\NeuronAIStudio\Support;

class StudioLocale
{
    /**
     * Resolve the locale Studio should use for the current request.
     * Config override wins when non-empty; otherwise the host app locale.
     */
    public static function resolve(): string
    {
        $override = config('neuronai-studio.locale');

        if (is_string($override) && $override !== '') {
            return $override;
        }

        return app()->getLocale() ?: 'en';
    }

    /**
     * Apply the resolved Studio locale for the current request lifecycle.
     */
    public static function apply(): void
    {
        $locale = self::resolve();
        app()->setLocale($locale);

        if (method_exists(app(), 'setFallbackLocale')) {
            app()->setFallbackLocale('en');
        }
    }
}
