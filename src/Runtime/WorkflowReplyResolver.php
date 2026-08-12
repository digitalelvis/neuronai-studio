<?php

namespace DigitalElvis\NeuronAIStudio\Runtime;

use DigitalElvis\NeuronAIStudio\Models\StudioRun;

/**
 * Canonical user-facing reply for a workflow run.
 *
 * Prefer explicit Stop `state.reply` or Human pause prompt; fall back to the
 * legacy last non-meta string only for graphs that never declared a reply.
 */
class WorkflowReplyResolver
{
    public const STATE_KEY = 'reply';

    /**
     * @param  array<string, mixed>|null  $output
     */
    public function textFromOutput(?array $output, ?string $humanPrompt = null): string
    {
        if (is_string($humanPrompt) && trim($humanPrompt) !== '') {
            return $humanPrompt;
        }

        if (! is_array($output)) {
            return '';
        }

        if (array_key_exists(self::STATE_KEY, $output)) {
            return $this->stringify($output[self::STATE_KEY]);
        }

        return $this->legacyLastString($output);
    }

    public function textFromRun(StudioRun $run, ?string $humanPrompt = null): string
    {
        $prompt = $humanPrompt;
        if ($prompt === null && in_array($run->status, ['awaiting_input', 'awaiting_tool_approval'], true)) {
            $prompt = $this->promptFromCheckpoint($run);
        }

        if (in_array($run->status, ['awaiting_input'], true) && is_string($prompt) && trim($prompt) !== '') {
            return trim($prompt);
        }

        $output = is_array($run->output) ? $run->output : [];

        return $this->textFromOutput($output, null);
    }

    public function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_bool($value) || is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? '' : $encoded;
    }

    /**
     * @param  array<string, mixed>  $output
     */
    protected function legacyLastString(array $output): string
    {
        $text = '';

        foreach ($output as $key => $value) {
            if (is_string($key) && str_starts_with($key, '__')) {
                continue;
            }

            if (in_array($key, ['input', 'attachments'], true)) {
                continue;
            }

            if (is_string($value) && $value !== '') {
                $text = $value;
            }
        }

        return $text;
    }

    protected function promptFromCheckpoint(StudioRun $run): ?string
    {
        $checkpoint = is_array($run->checkpoint_state) ? $run->checkpoint_state : [];
        $state = is_array($checkpoint['state'] ?? null) ? $checkpoint['state'] : [];

        // Prefer the prompt stamped when pausing (WRC-03).
        $prompt = $checkpoint['prompt'] ?? $state['__studio_human_prompt'] ?? null;

        return is_string($prompt) ? $prompt : null;
    }
}
