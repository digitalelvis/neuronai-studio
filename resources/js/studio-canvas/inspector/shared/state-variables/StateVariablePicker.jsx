import * as React from 'react';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import {
    filterStateVariables,
    groupStateVariables,
    normalizeStateKey,
} from '../stateVariables';
import StateVariableBadge, { GROUP_HEADER_STYLES } from './StateVariableBadge';

/**
 * @param {{
 *   open: boolean,
 *   onOpenChange: (open: boolean) => void,
 *   variables?: import('../stateVariables').StateVariable[],
 *   onSelect: (variable: import('../stateVariables').StateVariable) => void,
 *   trigger?: React.ReactNode,
 *   align?: 'start'|'center'|'end',
 *   side?: 'top'|'right'|'bottom'|'left',
 *   className?: string,
 *   emptyText?: string,
 * }} props
 */
export default function StateVariablePicker({
    open,
    onOpenChange,
    variables = [],
    onSelect,
    trigger,
    align = 'start',
    side = 'bottom',
    className,
    emptyText = 'No state variables found.',
}) {
    const [query, setQuery] = React.useState('');
    const inputRef = React.useRef(null);

    React.useEffect(() => {
        if (open) {
            const timer = window.setTimeout(() => inputRef.current?.focus(), 0);
            return () => window.clearTimeout(timer);
        }
        setQuery('');
        return undefined;
    }, [open]);

    const filtered = React.useMemo(
        () => filterStateVariables(variables, query),
        [variables, query],
    );
    const sections = React.useMemo(() => groupStateVariables(filtered), [filtered]);
    const normalizedKey = normalizeStateKey(query);
    const canUseCustom =
        normalizedKey !== '' &&
        !variables.some((variable) => variable.key === normalizedKey);

    const selectCustom = () => {
        if (!canUseCustom) {
            return;
        }
        onSelect?.({
            key: normalizedKey,
            label: normalizedKey,
            type: 'string',
            group: normalizedKey.startsWith('__') ? 'system' : 'node',
            sourceLabel: normalizedKey.startsWith('__') ? 'SYSTEM' : 'Custom',
        });
        onOpenChange?.(false);
    };

    const selectFirstFiltered = () => {
        const first = filtered[0];
        if (!first) {
            return false;
        }
        onSelect?.(first);
        onOpenChange?.(false);
        return true;
    };

    const handleEnter = (event) => {
        event.preventDefault();
        if (canUseCustom) {
            selectCustom();
            return;
        }
        if (filtered.length === 1) {
            selectFirstFiltered();
            return;
        }
        // Exact match on an existing key (e.g. typed {{input}} when input exists).
        if (normalizedKey !== '') {
            const exact = variables.find((variable) => variable.key === normalizedKey);
            if (exact) {
                onSelect?.(exact);
                onOpenChange?.(false);
            }
        }
    };

    return (
        <Popover open={open} onOpenChange={onOpenChange}>
            {trigger ? <PopoverTrigger asChild>{trigger}</PopoverTrigger> : null}
            <PopoverContent
                align={align}
                side={side}
                className={cn('w-72 p-0', className)}
                data-state-var-picker=""
                onOpenAutoFocus={(event) => event.preventDefault()}
                onCloseAutoFocus={(event) => event.preventDefault()}
            >
                <div className="border-b border-border p-2">
                    <Input
                        ref={inputRef}
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                handleEnter(event);
                            }
                        }}
                        placeholder="Search or type {{key}}…"
                        className="h-8"
                    />
                </div>
                <div className="max-h-64 overflow-y-auto p-1">
                    {canUseCustom && (
                        <button
                            type="button"
                            className="mb-1 flex w-full items-center rounded-sm px-2 py-1.5 text-left text-sm hover:bg-accent hover:text-accent-foreground"
                            onClick={selectCustom}
                        >
                            Use <code className="mx-1 rounded bg-muted px-1 text-xs">{normalizedKey}</code>
                        </button>
                    )}
                    {sections.length === 0 && !canUseCustom ? (
                        <p className="px-2 py-3 text-center text-xs text-muted-foreground">
                            {emptyText}
                        </p>
                    ) : (
                        sections.map((section) => {
                            const group = section.group || section.variables[0]?.group || 'node';
                            return (
                                <div key={section.id} className="mb-1">
                                    <div
                                        className={cn(
                                            'px-2 py-1 text-[10px] font-semibold uppercase tracking-wide',
                                            GROUP_HEADER_STYLES[group] || 'text-muted-foreground',
                                        )}
                                    >
                                        {section.title}
                                    </div>
                                    {section.variables.map((variable) => (
                                        <button
                                            key={`${section.id}:${variable.key}`}
                                            type="button"
                                            className="flex w-full items-center rounded-sm px-2 py-1.5 text-left hover:bg-accent hover:text-accent-foreground"
                                            onClick={() => {
                                                onSelect?.(variable);
                                                onOpenChange?.(false);
                                            }}
                                        >
                                            <StateVariableBadge
                                                variable={variable}
                                                hideSource
                                                className="max-w-full"
                                            />
                                        </button>
                                    ))}
                                </div>
                            );
                        })
                    )}
                </div>
            </PopoverContent>
        </Popover>
    );
}
