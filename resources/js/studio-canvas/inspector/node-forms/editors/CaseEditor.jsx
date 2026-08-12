import { useState } from 'react';
import { ChevronDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { sanitizeIntentId, uniqueIntentId } from '../../../graph';
import { StateVariableSelect } from '../../shared/state-variables';
import ConditionOperatorFields from '../fields/ConditionOperatorFields';

function normalizeCases(cases) {
    if (!Array.isArray(cases)) {
        return [];
    }

    return cases
        .filter((item) => item && typeof item === 'object')
        .map((item, index) => {
            const rawId =
                typeof item.id === 'string' && item.id !== ''
                    ? item.id
                    : `case_${index + 1}`;
            return {
                id: rawId,
                label: typeof item.label === 'string' && item.label !== '' ? item.label : rawId,
                state_key: typeof item.state_key === 'string' ? item.state_key : 'input',
                operator: typeof item.operator === 'string' ? item.operator : 'not_empty',
                value: item.value ?? null,
                value_type: typeof item.value_type === 'string' ? item.value_type : 'auto',
                strict: Boolean(item.strict),
            };
        });
}

function ensureUniqueIds(cases) {
    const seen = new Set();
    return cases.map((caseItem) => {
        const id = uniqueIntentId(caseItem.id, [...seen]);
        seen.add(id);
        return { ...caseItem, id };
    });
}

function formatCaseSummary(caseItem) {
    const parts = [caseItem.state_key || 'input', caseItem.operator || 'not_empty'];
    if (caseItem.value !== null && caseItem.value !== undefined && caseItem.value !== '') {
        parts.push(String(caseItem.value));
    }

    return parts.join(' · ');
}

export default function CaseEditor({ data, readOnly, currentNodeId, onUpdate }) {
    const cases = normalizeCases(data.cases);
    const [collapsedIds, setCollapsedIds] = useState(() => new Set());

    const commit = (next) => {
        onUpdate?.({ ...data, cases: ensureUniqueIds(next) });
    };

    const setCaseOpen = (id, open) => {
        setCollapsedIds((current) => {
            const next = new Set(current);
            if (open) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });
    };

    const addCase = () => {
        const nextIndex = cases.length + 1;
        const id = uniqueIntentId(`case_${nextIndex}`, cases.map((item) => item.id));
        commit([
            ...cases,
            {
                id,
                label: `Case ${nextIndex}`,
                state_key: 'input',
                operator: 'not_empty',
                value: null,
                value_type: 'auto',
                strict: false,
            },
        ]);
    };

    const updateCase = (index, patch) => {
        commit(cases.map((caseItem, i) => (i === index ? { ...caseItem, ...patch } : caseItem)));
    };

    const removeCase = (index) => {
        const removed = cases[index];
        if (removed) {
            setCaseOpen(removed.id, true);
        }
        commit(cases.filter((_, i) => i !== index));
    };

    const syncIdFromLabel = (index) => {
        const current = cases[index];
        if (!current) {
            return;
        }

        const sanitizedId = sanitizeIntentId(current.id);
        const idIsInvalid = current.id !== sanitizedId;
        const shouldSyncId =
            !current.id ||
            idIsInvalid ||
            current.id === sanitizeIntentId(current.label) ||
            current.id.startsWith('case_');

        if (!shouldSyncId) {
            return;
        }

        const nextId = uniqueIntentId(
            sanitizeIntentId(current.label) || current.id,
            cases.map((item) => item.id).filter((_, i) => i !== index),
        );
        updateCase(index, { id: nextId });
    };

    return (
        <div className="space-y-3">
            {cases.length === 0 && (
                <p className="text-xs text-muted-foreground">
                    Add cases to route execution. The first matching case wins; unmatched flows use
                    the default handle.
                </p>
            )}

            {cases.map((caseItem, index) => {
                const collapsed = collapsedIds.has(caseItem.id);

                return (
                    <Collapsible
                        key={`${caseItem.id}-${index}`}
                        open={!collapsed}
                        onOpenChange={(open) => setCaseOpen(caseItem.id, open)}
                        className="rounded-md border border-border"
                    >
                        <div className="flex items-start gap-1 px-3 py-2">
                            <CollapsibleTrigger className="flex min-w-0 flex-1 items-center gap-2 rounded-md py-1 text-left hover:bg-muted/40">
                                <ChevronDown
                                    className={cn(
                                        'h-3.5 w-3.5 shrink-0 text-muted-foreground transition-transform',
                                        collapsed && '-rotate-90',
                                    )}
                                />
                                <span className="min-w-0 flex-1">
                                    <span className="block text-xs font-medium text-muted-foreground">
                                        Case {index + 1}
                                        {collapsed && caseItem.label ? (
                                            <span className="ml-2 font-normal text-foreground">
                                                {caseItem.label}
                                            </span>
                                        ) : null}
                                    </span>
                                    {collapsed && (
                                        <span className="mt-0.5 block truncate text-[11px] text-muted-foreground">
                                            {formatCaseSummary(caseItem)}
                                        </span>
                                    )}
                                </span>
                            </CollapsibleTrigger>
                            {!readOnly && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => removeCase(index)}
                                >
                                    Remove
                                </Button>
                            )}
                        </div>

                        <CollapsibleContent className="space-y-3 px-3 pb-3">
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>Label</Label>
                                    <Input
                                        value={caseItem.label}
                                        onChange={(event) =>
                                            updateCase(index, { label: event.target.value })
                                        }
                                        onBlur={() => syncIdFromLabel(index)}
                                        disabled={readOnly}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Handle ID</Label>
                                    <Input
                                        value={caseItem.id}
                                        onChange={(event) =>
                                            updateCase(index, {
                                                id: sanitizeIntentId(event.target.value),
                                            })
                                        }
                                        disabled={readOnly}
                                    />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label>State Key</Label>
                                <StateVariableSelect
                                    value={caseItem.state_key}
                                    onChange={(key) => updateCase(index, { state_key: key })}
                                    currentNodeId={currentNodeId}
                                    disabled={readOnly}
                                />
                            </div>

                            <ConditionOperatorFields
                                operator={caseItem.operator}
                                value={caseItem.value}
                                valueType={caseItem.value_type}
                                strict={caseItem.strict}
                                onFieldsChange={(patch) => updateCase(index, patch)}
                                readOnly={readOnly}
                            />
                        </CollapsibleContent>
                    </Collapsible>
                );
            })}

            {!readOnly && (
                <Button type="button" variant="outline" size="sm" onClick={addCase}>
                    Add case
                </Button>
            )}
        </div>
    );
}

export function getSwitchCaseIds(config = {}) {
    return normalizeCases(config.cases).map((caseItem) => caseItem.id);
}
