import * as React from 'react';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import {
    filterStateVariables,
    groupStateVariables,
} from '../stateVariables';
import StateVariableBadge from './StateVariableBadge';

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
    const trimmedQuery = query.trim();
    const canUseCustom =
        trimmedQuery !== '' &&
        !variables.some((variable) => variable.key === trimmedQuery) &&
        /^[\w.]+$/.test(trimmedQuery);

    const selectCustom = () => {
        if (!canUseCustom) {
            return;
        }
        onSelect?.({
            key: trimmedQuery,
            label: trimmedQuery,
            type: 'string',
            group: trimmedQuery.startsWith('__') ? 'system' : 'node',
            sourceLabel: trimmedQuery.startsWith('__') ? 'SYSTEM' : 'Custom',
        });
        onOpenChange?.(false);
    };

    return (
        <Popover open={open} onOpenChange={onOpenChange}>
            {trigger ? <PopoverTrigger asChild>{trigger}</PopoverTrigger> : null}
            <PopoverContent
                align={align}
                side={side}
                className={cn('w-72 p-0', className)}
                onOpenAutoFocus={(event) => event.preventDefault()}
            >
                <div className="border-b border-border p-2">
                    <Input
                        ref={inputRef}
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                event.preventDefault();
                                selectCustom();
                            }
                        }}
                        placeholder="Search variables…"
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
                            Use <code className="mx-1 rounded bg-muted px-1 text-xs">{trimmedQuery}</code>
                        </button>
                    )}
                    {sections.length === 0 && !canUseCustom ? (
                        <p className="px-2 py-3 text-center text-xs text-muted-foreground">
                            {emptyText}
                        </p>
                    ) : (
                        sections.map((section) => (
                            <div key={section.id} className="mb-1">
                                <div className="px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
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
                                        <StateVariableBadge variable={variable} className="max-w-full" />
                                    </button>
                                ))}
                            </div>
                        ))
                    )}
                </div>
            </PopoverContent>
        </Popover>
    );
}
