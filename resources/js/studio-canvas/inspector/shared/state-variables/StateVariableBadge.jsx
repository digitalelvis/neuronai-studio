import { Braces } from 'lucide-react';
import { cn } from '@/lib/utils';

const GROUP_STYLES = {
    start: 'border-sky-500/40 bg-sky-500/15 text-sky-100',
    node: 'border-primary/40 bg-primary/15 text-primary-foreground',
    system: 'border-amber-500/40 bg-amber-500/15 text-amber-100',
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
    onRemove,
}) {
    const resolvedKey = variable?.key ?? keyProp ?? '';
    const resolvedGroup = variable?.group ?? group;
    const resolvedSource = variable?.sourceLabel ?? sourceLabel;
    const resolvedType = variable?.type ?? type;
    const path =
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
            title={path}
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
