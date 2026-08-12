import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { StateVariableSelect } from '../../shared/state-variables';
import ConditionOperatorFields from '../fields/ConditionOperatorFields';

function defaultRule(data = {}) {
    return {
        state_key: data.state_key ?? 'input',
        operator: data.operator ?? 'not_empty',
        value: data.value ?? null,
        value_type: data.value_type ?? 'auto',
        strict: data.strict ?? false,
    };
}

export default function ConditionRuleFields({
    rule,
    readOnly = false,
    stateKeyLabel = 'State Key',
    onChange,
    currentNodeId,
}) {
    const updateField = (key, value) => {
        onChange?.({ ...rule, [key]: value });
    };

    return (
        <>
            <div className="space-y-2">
                <Label>{stateKeyLabel}</Label>
                <StateVariableSelect
                    value={rule.state_key ?? 'input'}
                    onChange={(key) => updateField('state_key', key)}
                    currentNodeId={currentNodeId}
                    disabled={readOnly}
                />
            </div>
            <ConditionOperatorFields
                operator={rule.operator}
                value={rule.value}
                valueType={rule.value_type}
                strict={rule.strict}
                onFieldsChange={(patch) => onChange?.({ ...rule, ...patch })}
                readOnly={readOnly}
            />
        </>
    );
}

export function normalizeConditionRules(data = {}) {
    if (Array.isArray(data.rules) && data.rules.length > 0) {
        return data.rules.map((rule) => defaultRule(rule));
    }

    return [defaultRule(data)];
}

export function ConditionRulesEditor({
    data,
    readOnly = false,
    currentNodeId,
    onUpdate,
    addLabel = 'Add rule',
}) {
    const rules = normalizeConditionRules(data);
    const logic = data.logic === 'any' ? 'any' : 'all';
    const useCompound = Array.isArray(data.rules) && data.rules.length > 0;

    const commitRules = (nextRules, patch = {}) => {
        onUpdate?.({
            ...data,
            ...patch,
            rules: nextRules,
        });
    };

    const updateRule = (index, nextRule) => {
        commitRules(rules.map((rule, i) => (i === index ? nextRule : rule)));
    };

    const addRule = () => {
        commitRules([...rules, defaultRule()], { logic });
    };

    const removeRule = (index) => {
        const nextRules = rules.filter((_, i) => i !== index);
        if (nextRules.length === 0) {
            const { rules: _removed, logic: _logic, ...rest } = data;
            onUpdate?.({ ...rest, ...defaultRule() });
            return;
        }
        commitRules(nextRules);
    };

    const enableCompound = () => {
        commitRules(rules.length > 0 ? rules : [defaultRule(data)], { logic: 'all' });
    };

    if (!useCompound) {
        return (
            <div className="space-y-3">
                <ConditionRuleFields
                    rule={defaultRule(data)}
                    readOnly={readOnly}
                    currentNodeId={currentNodeId}
                    onChange={(nextRule) => {
                        onUpdate?.({
                            ...data,
                            state_key: nextRule.state_key,
                            operator: nextRule.operator,
                            value: nextRule.value,
                            value_type: nextRule.value_type,
                            strict: nextRule.strict,
                        });
                    }}
                />
                {!readOnly && (
                    <Button type="button" variant="outline" size="sm" onClick={enableCompound}>
                        Use multiple conditions
                    </Button>
                )}
            </div>
        );
    }

    return (
        <div className="space-y-3">
            <div className="space-y-2">
                <Label>Match</Label>
                <Select
                    value={logic}
                    onValueChange={(next) => onUpdate?.({ ...data, logic: next, rules })}
                    disabled={readOnly}
                >
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All rules (AND)</SelectItem>
                        <SelectItem value="any">Any rule (OR)</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            {rules.map((rule, index) => (
                <div key={`rule-${index}`} className="space-y-3 rounded-md border border-border p-3">
                    <div className="flex items-center justify-between gap-2">
                        <span className="text-xs font-medium text-muted-foreground">
                            Rule {index + 1}
                        </span>
                        {!readOnly && rules.length > 1 && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => removeRule(index)}
                            >
                                Remove
                            </Button>
                        )}
                    </div>
                    <ConditionRuleFields
                        rule={rule}
                        readOnly={readOnly}
                        currentNodeId={currentNodeId}
                        onChange={(nextRule) => updateRule(index, nextRule)}
                    />
                </div>
            ))}

            {!readOnly && (
                <Button type="button" variant="outline" size="sm" onClick={addRule}>
                    {addLabel}
                </Button>
            )}
        </div>
    );
}
