<?php

namespace DigitalElvis\NeuronAIStudio\Integration;

/**
 * Strips Studio-reserved keys (prefix `__`) from workflow state before
 * AG-UI STATE_SNAPSHOT / STATE_DELTA.
 */
final class ClientFacingState
{
    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function of(array $state): array
    {
        $visible = [];

        foreach ($state as $key => $value) {
            if (str_starts_with((string) $key, '__')) {
                continue;
            }

            $visible[$key] = $value;
        }

        return $visible;
    }
}
