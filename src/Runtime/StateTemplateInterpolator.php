<?php

namespace DigitalElvis\NeuronAIStudio\Runtime;

use NeuronAI\Workflow\WorkflowState;

class StateTemplateInterpolator
{
    public static function interpolate(string $template, WorkflowState $state): string
    {
        return preg_replace_callback('/\{\{\s*([\w.]+)\s*\}\}/', function (array $matches) use ($state) {
            $value = WorkflowStateValue::get($state, $matches[1]);

            if (is_array($value)) {
                return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
            }

            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }

            return (string) ($value ?? '');
        }, $template) ?? $template;
    }
}
