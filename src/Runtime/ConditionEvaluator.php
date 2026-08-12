<?php

namespace DigitalElvis\NeuronAIStudio\Runtime;

use DateTimeInterface;
use Exception;
use NeuronAI\Workflow\WorkflowState;

class ConditionEvaluator
{
    public const VALUE_TYPE_AUTO = 'auto';

    public const VALUE_TYPE_STRING = 'string';

    public const VALUE_TYPE_NUMBER = 'number';

    public const VALUE_TYPE_BOOLEAN = 'boolean';

    public const VALUE_TYPE_NULL = 'null';

    public const VALUE_TYPE_DATE = 'date';

    /** @var list<string> */
    public const ORDER_OPERATORS = ['gt', 'gte', 'lt', 'lte'];

    /**
     * @param  array<string, mixed>  $rule
     */
    public function evaluateRule(array $rule, WorkflowState $state): bool
    {
        $key = (string) ($rule['state_key'] ?? 'input');
        $operator = (string) ($rule['operator'] ?? 'not_empty');
        $value = $rule['value'] ?? null;
        $valueType = (string) ($rule['value_type'] ?? self::VALUE_TYPE_AUTO);
        $strict = (bool) ($rule['strict'] ?? false);
        $stateValue = WorkflowStateValue::get($state, $key);

        return $this->matches($stateValue, $operator, $value, $valueType, $strict);
    }

    public function matches(
        mixed $stateValue,
        string $operator,
        mixed $value = null,
        string $valueType = self::VALUE_TYPE_AUTO,
        bool $strict = false,
    ): bool {
        $normalizedValue = $this->normalizeConditionValue($value, $valueType);
        $orderComparison = $this->compareOrdered($stateValue, $normalizedValue, $valueType);

        return match ($operator) {
            'equals' => $this->compareEquals($stateValue, $normalizedValue, $strict, $valueType),
            'not_equals' => ! $this->compareEquals($stateValue, $normalizedValue, $strict, $valueType),
            'contains' => is_string($stateValue) && str_contains($stateValue, (string) $normalizedValue),
            'empty', 'is_empty' => empty($stateValue),
            'is_true' => $this->coerceBoolean($stateValue) === true,
            'is_false' => $this->coerceBoolean($stateValue) === false,
            'is_null' => $stateValue === null,
            'is_not_null' => $stateValue !== null,
            'gt' => $orderComparison !== null && $orderComparison > 0,
            'gte' => $orderComparison !== null && $orderComparison >= 0,
            'lt' => $orderComparison !== null && $orderComparison < 0,
            'lte' => $orderComparison !== null && $orderComparison <= 0,
            default => ! empty($stateValue),
        };
    }

    public function normalizeConditionValue(mixed $value, string $valueType = self::VALUE_TYPE_AUTO): mixed
    {
        if ($valueType === self::VALUE_TYPE_NULL) {
            return null;
        }

        if ($valueType === self::VALUE_TYPE_DATE) {
            if ($value === null) {
                return null;
            }

            return is_string($value) ? trim($value) : (string) $value;
        }

        if ($valueType === self::VALUE_TYPE_BOOLEAN) {
            if (is_bool($value)) {
                return $value;
            }

            if (is_string($value)) {
                return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
            }

            return (bool) $value;
        }

        if ($valueType === self::VALUE_TYPE_NUMBER) {
            return $this->parseNumber($value);
        }

        if ($valueType === self::VALUE_TYPE_STRING) {
            return $value === null ? '' : (string) $value;
        }

        return $this->inferConditionValue($value);
    }

    public function inferConditionValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        if ($trimmed === 'null') {
            return null;
        }

        if ($trimmed === 'true') {
            return true;
        }

        if ($trimmed === 'false') {
            return false;
        }

        if (is_numeric($trimmed)) {
            return str_contains($trimmed, '.') ? (float) $trimmed : (int) $trimmed;
        }

        return $value;
    }

    public function operatorRequiresValue(string $operator): bool
    {
        return in_array($operator, ['equals', 'not_equals', 'contains', ...self::ORDER_OPERATORS], true);
    }

    public static function buildCodegenCondition(
        string $stateExpr,
        string $operator,
        string $normalizedValue,
        string $valueType,
        bool $strict,
    ): string {
        $evaluatorClass = self::class;

        if (in_array($operator, self::ORDER_OPERATORS, true)) {
            return self::buildOrderCodegenCondition($stateExpr, $operator, $normalizedValue, $valueType);
        }

        if (in_array($operator, ['equals', 'not_equals'], true)
            && ($valueType === self::VALUE_TYPE_BOOLEAN || $normalizedValue === 'true' || $normalizedValue === 'false')) {
            $left = "{$evaluatorClass}::coerceBooleanForCodegen({$stateExpr})";
            $right = "{$evaluatorClass}::coerceBooleanForCodegen({$normalizedValue})";

            return $operator === 'equals'
                ? "({$left} !== null && {$right} !== null && {$left} === {$right})"
                : "({$left} !== null && {$right} !== null && {$left} !== {$right})";
        }

        return match ($operator) {
            'equals' => $strict
                ? "{$stateExpr} === {$normalizedValue}"
                : "{$stateExpr} == {$normalizedValue}",
            'not_equals' => $strict
                ? "{$stateExpr} !== {$normalizedValue}"
                : "{$stateExpr} != {$normalizedValue}",
            'contains' => "is_string({$stateExpr}) && str_contains({$stateExpr}, (string) {$normalizedValue})",
            'empty', 'is_empty' => "empty({$stateExpr})",
            'is_true' => "({$evaluatorClass}::coerceBooleanForCodegen({$stateExpr}) === true)",
            'is_false' => "({$evaluatorClass}::coerceBooleanForCodegen({$stateExpr}) === false)",
            'is_null' => "{$stateExpr} === null",
            'is_not_null' => "{$stateExpr} !== null",
            default => "! empty({$stateExpr})",
        };
    }

    public static function coerceBooleanForCodegen(mixed $value): ?bool
    {
        return (new self)->coerceBoolean($value);
    }

    public static function parseDateTimestamp(mixed $value): ?int
    {
        if ($value instanceof DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($trimmed))->getTimestamp();
        } catch (Exception) {
            $timestamp = strtotime($trimmed);

            return $timestamp !== false ? $timestamp : null;
        }
    }

    protected static function buildOrderCodegenCondition(
        string $stateExpr,
        string $operator,
        string $normalizedValue,
        string $valueType,
    ): string {
        $evaluatorClass = self::class;

        if ($valueType === self::VALUE_TYPE_DATE) {
            $left = "{$evaluatorClass}::parseDateTimestamp({$stateExpr})";
            $right = "{$evaluatorClass}::parseDateTimestamp({$normalizedValue})";
        } else {
            $left = "({$evaluatorClass}::parseNumberForCodegen({$stateExpr}))";
            $right = "({$evaluatorClass}::parseNumberForCodegen({$normalizedValue}))";
        }

        $comparison = match ($operator) {
            'gt' => '> 0',
            'gte' => '>= 0',
            'lt' => '< 0',
            'lte' => '<= 0',
            default => '> 0',
        };

        return "({$left} !== null && {$right} !== null && (({$left} <=> {$right}) {$comparison}))";
    }

    public static function parseNumberForCodegen(mixed $value): int|float|null
    {
        return (new self)->parseNumber($value);
    }

    protected function compareOrdered(mixed $stateValue, mixed $normalizedValue, string $valueType): ?int
    {
        if ($valueType === self::VALUE_TYPE_DATE) {
            $left = self::parseDateTimestamp($stateValue);
            $right = self::parseDateTimestamp($normalizedValue);

            if ($left === null || $right === null) {
                return null;
            }

            return $left <=> $right;
        }

        $left = $this->parseNumber($stateValue);
        $right = $this->parseNumber($normalizedValue);

        if ($left === null || $right === null) {
            return null;
        }

        return $left <=> $right;
    }

    protected function parseNumber(mixed $value): int|float|null
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric(trim($value))) {
            $trimmed = trim($value);

            return str_contains($trimmed, '.') ? (float) $trimmed : (int) $trimmed;
        }

        return null;
    }

    /**
     * Coerce common boolean representations from state / UI literals.
     * Set State and templates typically store "true"/"false" strings, not PHP bools.
     */
    protected function coerceBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            if ($value === 1 || $value === 1.0) {
                return true;
            }
            if ($value === 0 || $value === 0.0) {
                return false;
            }

            return null;
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return filter_var($trimmed, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    protected function compareEquals(
        mixed $stateValue,
        mixed $normalizedValue,
        bool $strict,
        string $valueType = self::VALUE_TYPE_AUTO,
    ): bool {
        $treatAsBoolean = $valueType === self::VALUE_TYPE_BOOLEAN
            || is_bool($normalizedValue)
            || (is_string($normalizedValue) && in_array(strtolower(trim($normalizedValue)), ['true', 'false'], true));

        if ($treatAsBoolean) {
            $left = $this->coerceBoolean($stateValue);
            $right = is_bool($normalizedValue)
                ? $normalizedValue
                : $this->coerceBoolean($normalizedValue);

            if ($left === null || $right === null) {
                return false;
            }

            return $left === $right;
        }

        if ($strict) {
            return $stateValue === $normalizedValue;
        }

        return $stateValue == $normalizedValue;
    }
}
