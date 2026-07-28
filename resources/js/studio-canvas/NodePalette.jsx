import { useMemo, useState } from 'react';
import { ChevronDown, Search } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { NodeTypeIcon } from './nodes/nodeIcons';
import { t } from '@/lib/i18n';

const CATEGORY_ORDER = ['ai', 'logic', 'flow', 'utilities'];
const CATEGORY_LABEL_KEYS = {
    ai: 'palette.category.ai',
    logic: 'palette.category.logic',
    flow: 'palette.category.flow',
    utilities: 'palette.category.utilities',
};

const CATALOG_ORDER = ['tools', 'mcp'];
const CATALOG_LABEL_KEYS = {
    tools: 'palette.category.tools',
    mcp: 'palette.category.mcp',
};

function matchesQuery(haystacks, query) {
    if (!query) {
        return true;
    }

    return haystacks.some((value) => String(value || '').toLowerCase().includes(query));
}

export default function NodePalette({
    nodeTypes = {},
    tools = [],
    mcpServers = [],
    readOnly = false,
}) {
    const [query, setQuery] = useState('');
    const [openCategories, setOpenCategories] = useState(() =>
        Object.fromEntries([...CATEGORY_ORDER, ...CATALOG_ORDER].map((key) => [key, true])),
    );

    const q = query.trim().toLowerCase();

    const paletteTypes = useMemo(() => {
        return Object.entries(nodeTypes)
            .filter(([type]) => !['start', 'stop'].includes(type))
            .filter(([type, meta]) =>
                matchesQuery([meta.label ?? type, type, meta.category], q),
            );
    }, [nodeTypes, q]);

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
            label: t(CATEGORY_LABEL_KEYS[key] || key),
            items: groups[key],
        }));
    }, [paletteTypes]);

    const catalogTools = useMemo(
        () =>
            tools.filter(
                (tool) =>
                    !String(tool.ref || '').startsWith('mcp:') &&
                    matchesQuery([tool.label, tool.ref, tool.description, tool.category], q),
            ),
        [tools, q],
    );

    const catalogMcp = useMemo(
        () =>
            mcpServers.filter((server) =>
                matchesQuery([server.label, server.slug, server.description], q),
            ),
        [mcpServers, q],
    );

    const setSectionOpen = (key, open) => {
        setOpenCategories((current) => ({ ...current, [key]: open }));
    };

    return (
        <aside
            className={`ab-node-palette flex h-full min-h-0 flex-col overflow-hidden border-r border-border bg-card/40 ${readOnly ? 'opacity-60' : ''}`}
        >
            <div className="shrink-0 space-y-2 border-b border-border p-3">
                <h3 className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{t('palette.components')}</h3>
                <div className="relative">
                    <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        className="h-8 pl-8 text-xs"
                        placeholder={t('palette.search')}
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        disabled={readOnly}
                    />
                </div>
                <p className="text-[11px] text-muted-foreground">
                    {readOnly ? t('palette.readonly') : t('palette.drag_hint')}
                </p>
            </div>

            <div className="min-h-0 flex-1 overflow-auto p-2">
                {grouped.length === 0 && catalogTools.length === 0 && catalogMcp.length === 0 && (
                    <p className="px-2 py-4 text-center text-xs text-muted-foreground">{t('palette.no_match')}</p>
                )}

                {grouped.map((group) => (
                    <Collapsible
                        key={group.key}
                        open={openCategories[group.key] !== false}
                        onOpenChange={(open) => setSectionOpen(group.key, open)}
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

                {catalogTools.length > 0 && (
                    <Collapsible
                        open={openCategories.tools !== false}
                        onOpenChange={(open) => setSectionOpen('tools', open)}
                        className="mb-1"
                    >
                        <CollapsibleTrigger className="flex w-full items-center justify-between rounded-md px-2 py-1.5 text-left text-[11px] font-semibold uppercase tracking-wide text-muted-foreground hover:bg-muted/40">
                            {t(CATALOG_LABEL_KEYS.tools)}
                            <ChevronDown
                                className={`h-3.5 w-3.5 transition-transform ${openCategories.tools === false ? '-rotate-90' : ''}`}
                            />
                        </CollapsibleTrigger>
                        <CollapsibleContent className="space-y-1 pb-2 pt-1">
                            {catalogTools.map((tool) => (
                                <div
                                    key={tool.ref}
                                    className="ab-palette-item flex cursor-grab items-center gap-2 rounded-md border border-transparent bg-muted/20 px-2.5 py-2 text-sm transition-colors hover:border-border hover:bg-muted/50 active:cursor-grabbing"
                                    draggable={!readOnly}
                                    data-canvas-node-type="tool"
                                    data-tool-ref={tool.ref}
                                    title={tool.description || tool.ref}
                                    role="button"
                                    tabIndex={0}
                                >
                                    <span className="flex h-6 w-6 items-center justify-center rounded-md bg-background text-muted-foreground">
                                        <NodeTypeIcon name="wrench" />
                                    </span>
                                    <span className="truncate">{tool.label || tool.ref}</span>
                                </div>
                            ))}
                        </CollapsibleContent>
                    </Collapsible>
                )}

                {catalogMcp.length > 0 && (
                    <Collapsible
                        open={openCategories.mcp !== false}
                        onOpenChange={(open) => setSectionOpen('mcp', open)}
                        className="mb-1"
                    >
                        <CollapsibleTrigger className="flex w-full items-center justify-between rounded-md px-2 py-1.5 text-left text-[11px] font-semibold uppercase tracking-wide text-muted-foreground hover:bg-muted/40">
                            {t(CATALOG_LABEL_KEYS.mcp)}
                            <ChevronDown
                                className={`h-3.5 w-3.5 transition-transform ${openCategories.mcp === false ? '-rotate-90' : ''}`}
                            />
                        </CollapsibleTrigger>
                        <CollapsibleContent className="space-y-1 pb-2 pt-1">
                            {catalogMcp.map((server) => (
                                <div
                                    key={server.slug}
                                    className="ab-palette-item flex cursor-grab items-center gap-2 rounded-md border border-transparent bg-muted/20 px-2.5 py-2 text-sm transition-colors hover:border-border hover:bg-muted/50 active:cursor-grabbing"
                                    draggable={!readOnly}
                                    data-canvas-node-type="mcp"
                                    data-mcp-server={server.slug}
                                    title={server.description || server.slug}
                                    role="button"
                                    tabIndex={0}
                                >
                                    <span className="flex h-6 w-6 items-center justify-center rounded-md bg-background text-muted-foreground">
                                        <NodeTypeIcon name="plug" />
                                    </span>
                                    <span className="truncate">{server.label || server.slug}</span>
                                </div>
                            ))}
                        </CollapsibleContent>
                    </Collapsible>
                )}
            </div>
        </aside>
    );
}
