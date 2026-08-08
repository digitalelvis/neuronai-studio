import { Braces } from 'lucide-react';
import { cn } from '@/lib/utils';

export const GROUP_STYLES = {
    start: 'border-sky-500/40 bg-sky-500/15 text-sky-100',
    initial: 'border-emerald-500/40 bg-emerald-500/15 text-emerald-100',
    node: 'border-primary/40 bg-primary/15 text-primary-foreground',
    system: 'border-amber-500/40 bg-amber-500/15 text-amber-100',
};

export const GROUP_HEADER_STYLES = {
    start: 'text-sky-300',
    initial: 'text-emerald-300',
    node: 'text-primary',
    system: 'text-amber-300',
};

/**
 * @param {{
 *   variable?: { key: string, label?: string, type?: string, group?: string, sourceLabel?: string },
 *   key?: string,
 *   group?: string,
 *   sourceLabel?: string,
 *   type?: string,
 *   className?: string,
 *   compact?: boolean,
 *   hideSource?: boolean,
 *   onRemove?: () => void,
 * }} props
 */
export default function StateVariableBadge({
    variable,
    key: keyProp,
    group = 'node',
    sourceLabel,
    type = 'string',
    className,
    compact = false,
    hideSource = false,
    onRemove,
}) {
    const resolvedKey = variable?.key ?? keyProp ?? '';
    const resolvedGroup = variable?.group ?? group;
    const resolvedSource = variable?.sourceLabel ?? sourceLabel;
    const resolvedType = variable?.type ?? type;
    const path =
        !hideSource && resolvedSource && resolvedSource !== resolvedKey
            ? `${resolvedSource} / ${resolvedKey}`
            : resolvedKey;
    const titlePath =
        resolvedSource && resolvedSource !== resolvedKey
            ? `${resolvedSource} / ${resolvedKey}`
            : resolvedKey;

    return (
        <span
            className={cn(
                'inline-flex max-w-full items-center gap-1 rounded-md border px-1.5 py-0.5 text-[11px] font-medium leading-tight',
                GROUP_STYLES[resolvedGroup] || GROUP_STYLES.node,
                className,
            )}
            title={titlePath}
        >
            <Braces className="h-3 w-3 shrink-0 opacity-80" aria-hidden />
            <span className="truncate">{path}</span>
            {!compact && resolvedType && (
                <span className="shrink-0 text-[10px] font-normal opacity-60">{resolvedType}</span>
            )}
            {typeof onRemove === 'function' && (
                <button
                    type="button"
                    className="ml-0.5 shrink-0 rounded-sm opacity-70 hover:opacity-100"
                    onClick={(event) => {
                        event.preventDefault();
                        event.stopPropagation();
                        onRemove();
                    }}
                    aria-label={`Remove ${resolvedKey}`}
                >
                    ×
                </button>
            )}
        </span>
    );
}
