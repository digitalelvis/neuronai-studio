import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Combobox } from '@/components/ui/combobox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const VALUE_OPERATORS = ['equals', 'not_equals', 'contains'];
const ORDER_OPERATORS = ['gt', 'gte', 'lt', 'lte'];
const VALUELESS_OPERATORS = ['not_empty', 'empty', 'is_empty', 'is_true', 'is_false', 'is_null', 'is_not_null'];
const COMPARABLE_VALUE_TYPES = ['number', 'date'];

const OPERATOR_OPTIONS = [
    { value: 'not_empty', label: 'is not empty' },
    { value: 'empty', label: 'is empty' },
    { value: 'is_empty', label: 'is empty (explicit)' },
    { value: 'is_true', label: 'is true' },
    { value: 'is_false', label: 'is false' },
    { value: 'is_null', label: 'is null' },
    { value: 'is_not_null', label: 'is not null' },
    { value: 'equals', label: 'equals' },
    { value: 'not_equals', label: 'does not equal' },
    { value: 'contains', label: 'contains' },
    { value: 'gt', label: 'greater than (number/date)' },
    { value: 'gte', label: 'greater or equal (number/date)' },
    { value: 'lt', label: 'less than (number/date)' },
    { value: 'lte', label: 'less or equal (number/date)' },
];

/**
 * Emit a single patch so parent state updates are atomic (avoids stale overwrites
 * when operator change also clears value / sets value_type).
 *
 * @param {{
 *   operator?: string,
 *   value?: unknown,
 *   valueType?: string,
 *   strict?: boolean,
 *   onFieldsChange?: (patch: Record<string, unknown>) => void,
 *   readOnly?: boolean,
 *   operatorLabel?: string,
 *   valueLabel?: string,
 * }} props
 */
export default function ConditionOperatorFields({
    operator,
    value,
    valueType = 'auto',
    strict = false,
    onFieldsChange,
    readOnly = false,
    operatorLabel = 'Operator',
    valueLabel = 'Value',
}) {
    const currentOperator = operator ?? 'not_empty';
    const isOrderOperator = ORDER_OPERATORS.includes(currentOperator);
    const showsValue = VALUE_OPERATORS.includes(currentOperator) || isOrderOperator;
    const showsValueType = ['equals', 'not_equals'].includes(currentOperator) || isOrderOperator;
    const resolvedValueType = isOrderOperator
        ? COMPARABLE_VALUE_TYPES.includes(valueType)
            ? valueType
            : 'number'
        : (valueType ?? 'auto');

    const patchFields = (patch) => {
        onFieldsChange?.(patch);
    };

    const handleOperatorChange = (nextOperator) => {
        const patch = { operator: nextOperator };
        if (VALUELESS_OPERATORS.includes(nextOperator)) {
            patch.value = null;
        }
        if (ORDER_OPERATORS.includes(nextOperator) && !COMPARABLE_VALUE_TYPES.includes(valueType)) {
            patch.value_type = 'number';
        }
        patchFields(patch);
    };

    return (
        <>
            <div className="space-y-2">
                <Label>{operatorLabel}</Label>
                <Combobox
                    options={OPERATOR_OPTIONS}
                    value={currentOperator}
                    onValueChange={handleOperatorChange}
                    placeholder="Select operator"
                    searchPlaceholder="Search operators…"
                    emptyText="No operators found."
                    disabled={readOnly}
                />
            </div>

            {showsValueType && (
                <div className="space-y-2">
                    <Label>{isOrderOperator ? 'Compare as' : 'Value type'}</Label>
                    <Select
                        value={resolvedValueType}
                        onValueChange={(next) => {
                            const patch = { value_type: next };
                            if (next === 'null') {
                                patch.value = null;
                            }
                            patchFields(patch);
                        }}
                        disabled={readOnly}
                    >
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {!isOrderOperator && (
                                <>
                                    <SelectItem value="auto">Auto-detect</SelectItem>
                                    <SelectItem value="string">String</SelectItem>
                                    <SelectItem value="boolean">Boolean</SelectItem>
                                    <SelectItem value="null">Null</SelectItem>
                                </>
                            )}
                            <SelectItem value="number">Number</SelectItem>
                            <SelectItem value="date">Date</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            )}

            {showsValue && resolvedValueType === 'boolean' && (
                <div className="space-y-2">
                    <Label>{valueLabel}</Label>
                    <Select
                        value={value === true || value === 'true' ? 'true' : 'false'}
                        onValueChange={(next) => patchFields({ value: next === 'true' })}
                        disabled={readOnly}
                    >
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="true">True</SelectItem>
                            <SelectItem value="false">False</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            )}

            {showsValue && resolvedValueType === 'null' && (
                <p className="text-xs text-muted-foreground">Compares against null.</p>
            )}

            {showsValue && resolvedValueType === 'number' && (
                <div className="space-y-2">
                    <Label>{valueLabel}</Label>
                    <Input
                        type="number"
                        value={value ?? ''}
                        onChange={(e) =>
                            patchFields({
                                value: e.target.value === '' ? null : Number(e.target.value),
                            })
                        }
                        disabled={readOnly}
                    />
                </div>
            )}

            {showsValue && resolvedValueType === 'date' && (
                <div className="space-y-2">
                    <Label>{valueLabel}</Label>
                    <Input
                        type="datetime-local"
                        value={value ?? ''}
                        onChange={(e) => patchFields({ value: e.target.value || null })}
                        disabled={readOnly}
                    />
                    <p className="text-xs text-muted-foreground">
                        Compares ISO/local datetime strings from state against this value.
                    </p>
                </div>
            )}

            {showsValue && (resolvedValueType === 'auto' || resolvedValueType === 'string') && (
                <div className="space-y-2">
                    <Label>{valueLabel}</Label>
                    <Input
                        value={value ?? ''}
                        onChange={(e) => patchFields({ value: e.target.value })}
                        disabled={readOnly}
                    />
                </div>
            )}

            {showsValueType && !isOrderOperator && (
                <div className="flex items-center gap-2">
                    <input
                        id={`strict-${operatorLabel}`}
                        type="checkbox"
                        checked={Boolean(strict)}
                        onChange={(event) => patchFields({ strict: event.target.checked })}
                        disabled={readOnly}
                        className="h-4 w-4 rounded border-input"
                    />
                    <Label htmlFor={`strict-${operatorLabel}`} className="text-xs font-normal text-muted-foreground">
                        Strict comparison (===)
                    </Label>
                </div>
            )}
        </>
    );
}

export { VALUELESS_OPERATORS, VALUE_OPERATORS, ORDER_OPERATORS, OPERATOR_OPTIONS };
