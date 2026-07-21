<?php

namespace DigitalElvis\NeuronAIStudio\Runtime;

use DigitalElvis\NeuronAIStudio\Runtime\Context\RagContextBudgeter;
use DigitalElvis\NeuronAIStudio\Runtime\Context\TokenBudgetTruncator;
use DigitalElvis\NeuronAIStudio\Runtime\Memory\MemoryConfig;
use NeuronAI\Workflow\WorkflowState;

class StateTemplateInterpolator
{
    /**
     * @param  list<array<string, mixed>>|null  $truncationEvents  Collected when budgets truncate.
     */
    public static function interpolate(
        string $template,
        WorkflowState $state,
        ?MemoryConfig $memory = null,
        ?array &$truncationEvents = null,
    ): string {
        $truncator = new TokenBudgetTruncator;
        $ragBudgeter = new RagContextBudgeter($truncator);

        return preg_replace_callback('/\{\{\s*([\w.]+)\s*\}\}/', function (array $matches) use ($state, $memory, $truncator, $ragBudgeter, &$truncationEvents) {
            $field = $matches[1];
            $value = WorkflowStateValue::get($state, $field);
            $string = self::stringify($value);

            if ($memory === null || $memory->isInherit()) {
                return $string;
            }

            if (self::isRagField($field)) {
                $budget = $memory->budgetRag();
                if ($budget === null) {
                    // RAG field without RAG budget: do not apply generic state budget (CTX-03).
                    return $string;
                }

                $ragText = self::ragTextForBudget($field, $value, $string);
                $result = $ragBudgeter->truncate($ragText, $budget);
                if ($result->truncated) {
                    $truncationEvents[] = [
                        'kind' => 'rag_context',
                        'field' => $field,
                        'tokens_before' => $result->tokensBefore,
                        'tokens_after' => $result->tokensAfter,
                        'strategy' => $result->strategy,
                    ];
                }

                return self::rebuildRagInterpolation($field, $value, $string, $ragText, $result->text);
            }

            $budget = $memory->budgetState();
            if ($budget === null) {
                return $string;
            }

            $result = $truncator->truncate($string, $budget);
            if ($result->truncated) {
                $truncationEvents[] = [
                    'kind' => 'state_field',
                    'field' => $field,
                    'tokens_before' => $result->tokensBefore,
                    'tokens_after' => $result->tokensAfter,
                    'strategy' => $result->strategy,
                ];
            }

            return $result->text;
        }, $template) ?? $template;
    }

    private static function stringify(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) ($value ?? '');
    }

    private static function isRagField(string $field): bool
    {
        return $field === 'rag_context' || str_starts_with($field, 'rag_context.');
    }

    private static function ragTextForBudget(string $field, mixed $value, string $stringified): string
    {
        if ($field === 'rag_context.context' || str_ends_with($field, '.context')) {
            return $stringified;
        }

        if (is_array($value) && isset($value['context']) && is_string($value['context'])) {
            return $value['context'];
        }

        return $stringified;
    }

    private static function rebuildRagInterpolation(
        string $field,
        mixed $value,
        string $originalString,
        string $ragText,
        string $truncatedText,
    ): string {
        if ($ragText === $originalString) {
            return $truncatedText;
        }

        if (is_array($value) && isset($value['context']) && is_string($value['context'])) {
            $copy = $value;
            $copy['context'] = $truncatedText;

            return json_encode($copy, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $truncatedText;
        }

        return $truncatedText;
    }
}
