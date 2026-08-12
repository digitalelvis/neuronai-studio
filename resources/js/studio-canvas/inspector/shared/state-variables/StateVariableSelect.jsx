import * as React from 'react';
import { ChevronsUpDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { stripTemplate, useAvailableStateVariables } from '../stateVariables';
import StateVariableBadge from './StateVariableBadge';
import StateVariablePicker from './StateVariablePicker';

/**
 * Single state-variable select. Persists the raw key (not `{{key}}`).
 * Clicking anywhere opens the picker; type a custom key (or `{{key}}`) in the
 * search input and press Enter to commit.
 *
 * @param {{
 *   value?: string,
 *   onChange?: (key: string) => void,
 *   currentNodeId?: string|null,
 *   nodes?: unknown[],
 *   edges?: unknown[],
 *   variables?: import('../stateVariables').StateVariable[],
 *   placeholder?: string,
 *   disabled?: boolean,
 *   className?: string,
 *   allowClear?: boolean,
 * }} props
 */
export default function StateVariableSelect({
    value = '',
    onChange,
    currentNodeId = null,
    nodes,
    edges,
    variables: variablesProp,
    placeholder = 'Select state variable…',
    disabled = false,
    className,
    allowClear = false,
}) {
    const [open, setOpen] = React.useState(false);
    const catalog = useAvailableStateVariables(currentNodeId, { nodes, edges });
    const variables = variablesProp ?? catalog;
    const rawValue = stripTemplate(value ?? '');
    const selected =
        variables.find((variable) => variable.key === rawValue) ||
        (rawValue
            ? {
                  key: rawValue,
                  label: rawValue,
                  type: 'string',
                  group: rawValue.startsWith('__') ? 'system' : 'node',
                  sourceLabel: rawValue.startsWith('__') ? 'SYSTEM' : 'Custom',
              }
            : null);

    return (
        <StateVariablePicker
            open={open}
            onOpenChange={(next) => {
                if (disabled) {
                    return;
                }
                setOpen(next);
            }}
            variables={variables}
            onSelect={(variable) => onChange?.(variable.key)}
            trigger={
                <Button
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    disabled={disabled}
                    className={cn(
                        'h-9 w-full justify-between gap-2 font-normal',
                        !selected && 'text-muted-foreground',
                        className,
                    )}
                >
                    {selected ? (
                        <StateVariableBadge variable={selected} className="min-w-0" />
                    ) : (
                        <span className="truncate">{placeholder}</span>
                    )}
                    <span className="flex shrink-0 items-center gap-1">
                        {allowClear && selected && !disabled && (
                            <span
                                role="button"
                                tabIndex={-1}
                                className="rounded px-1 text-muted-foreground hover:text-foreground"
                                onClick={(event) => {
                                    event.preventDefault();
                                    event.stopPropagation();
                                    onChange?.('');
                                }}
                            >
                                ×
                            </span>
                        )}
                        <ChevronsUpDown className="h-4 w-4 opacity-50" />
                    </span>
                </Button>
            }
        />
    );
}
