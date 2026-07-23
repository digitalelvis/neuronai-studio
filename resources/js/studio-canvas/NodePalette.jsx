import { useMemo, useState } from 'react';
import { ChevronDown, Search } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { NodeTypeIcon } from './nodes/nodeIcons';

const CATEGORY_ORDER = ['ai', 'logic', 'flow', 'utilities'];
const CATEGORY_LABELS = {
    ai: 'Models & Agents',
    logic: 'Flow Control',
    flow: 'Input & Output',
    utilities: 'Utilities',
};

export default function NodePalette({ nodeTypes = {}, readOnly = false }) {
    const [query, setQuery] = useState('');
    const [openCategories, setOpenCategories] = useState(() =>
        Object.fromEntries(CATEGORY_ORDER.map((key) => [key, true])),
    );

    const paletteTypes = useMemo(() => {
        const q = query.trim().toLowerCase();

        return Object.entries(nodeTypes)
            .filter(([type]) => !['start', 'stop'].includes(type))
            .filter(([type, meta]) => {
                if (!q) {
                    return true;
                }

                const label = (meta.label ?? type).toLowerCase();
                return label.includes(q) || type.includes(q) || (meta.category || '').includes(q);
            });
    }, [nodeTypes, query]);

    const grouped = useMemo(() => {
        const groups = {};

        for (const [type, meta] of paletteTypes) {
            const category = meta.category || 'flow';
            if (!groups[category]) {
                groups[category] = [];
            }
            groups[category].push([type, meta]);
        }

        return CATEGORY_ORDER.filter((key) => groups[key]?.length).map((key) => ({
            key,
            label: CATEGORY_LABELS[key] || key,
            items: groups[key],
        }));
    }, [paletteTypes]);

    return (
        <aside
            className={`ab-node-palette flex h-full min-h-0 flex-col overflow-hidden border-r border-border bg-card/40 ${readOnly ? 'opacity-60' : ''}`}
        >
            <div className="shrink-0 space-y-2 border-b border-border p-3">
                <h3 className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Components</h3>
                <div className="relative">
                    <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        className="h-8 pl-8 text-xs"
                        placeholder="Search nodes…"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        disabled={readOnly}
                    />
                </div>
                <p className="text-[11px] text-muted-foreground">
                    {readOnly ? 'Read-only preview' : 'Drag onto the canvas'}
                </p>
            </div>

            <div className="min-h-0 flex-1 overflow-auto p-2">
                {grouped.length === 0 && (
                    <p className="px-2 py-4 text-center text-xs text-muted-foreground">No matching nodes.</p>
                )}

                {grouped.map((group) => (
                    <Collapsible
                        key={group.key}
                        open={openCategories[group.key] !== false}
                        onOpenChange={(open) =>
                            setOpenCategories((current) => ({ ...current, [group.key]: open }))
                        }
                        className="mb-1"
                    >
                        <CollapsibleTrigger className="flex w-full items-center justify-between rounded-md px-2 py-1.5 text-left text-[11px] font-semibold uppercase tracking-wide text-muted-foreground hover:bg-muted/40">
                            {group.label}
                            <ChevronDown
                                className={`h-3.5 w-3.5 transition-transform ${openCategories[group.key] === false ? '-rotate-90' : ''}`}
                            />
                        </CollapsibleTrigger>
                        <CollapsibleContent className="space-y-1 pb-2 pt-1">
                            {group.items.map(([type, meta]) => (
                                <div
                                    key={type}
                                    className="ab-palette-item flex cursor-grab items-center gap-2 rounded-md border border-transparent bg-muted/20 px-2.5 py-2 text-sm transition-colors hover:border-border hover:bg-muted/50 active:cursor-grabbing"
                                    draggable={!readOnly}
                                    data-canvas-node-type={type}
                                    role="button"
                                    tabIndex={0}
                                >
                                    <span className="flex h-6 w-6 items-center justify-center rounded-md bg-background text-muted-foreground">
                                        <NodeTypeIcon name={meta.icon || 'circle'} />
                                    </span>
                                    <span className="truncate">{meta.label ?? type}</span>
                                </div>
                            ))}
                        </CollapsibleContent>
                    </Collapsible>
                ))}
            </div>
        </aside>
    );
}
