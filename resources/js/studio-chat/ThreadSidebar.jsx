import { useMemo, useState } from 'react';
import { Check, Copy, MoreHorizontal, PanelLeftClose, PanelLeft, Plus, Search } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

export default function ThreadSidebar({
    threads = [],
    activeThreadId = null,
    onSelectThread,
    onNewThread,
    collapsed = false,
    onCollapsedChange,
    loading = false,
    disabled = false,
}) {
    const [query, setQuery] = useState('');
    const [copiedId, setCopiedId] = useState(null);

    const filtered = useMemo(() => {
        const needle = query.trim().toLowerCase();
        if (!needle) {
            return threads;
        }

        return threads.filter((thread) => {
            const haystack = `${thread.label ?? ''} ${thread.preview ?? ''} ${thread.id ?? ''}`.toLowerCase();
            return haystack.includes(needle);
        });
    }, [threads, query]);

    const handleCopy = async (threadId) => {
        try {
            await navigator.clipboard.writeText(threadId);
            setCopiedId(threadId);
            window.setTimeout(() => setCopiedId(null), 1500);
        } catch {
            setCopiedId(null);
        }
    };

    if (collapsed) {
        return (
            <div className="flex h-full w-12 shrink-0 flex-col items-center border-r border-border bg-muted/20 py-3">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="h-8 w-8"
                    onClick={() => onCollapsedChange?.(false)}
                    title="Expand sidebar"
                >
                    <PanelLeft className="h-4 w-4" />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="mt-2 h-8 w-8"
                    onClick={onNewThread}
                    disabled={disabled}
                    title="New chat"
                >
                    <Plus className="h-4 w-4" />
                </Button>
            </div>
        );
    }

    return (
        <aside className="flex h-full w-[260px] shrink-0 flex-col border-r border-border bg-muted/20">
            <div className="flex items-center justify-between gap-2 border-b border-border px-3 py-3">
                <div className="flex min-w-0 items-center gap-2">
                    <span className="flex h-6 w-6 items-center justify-center rounded-md bg-primary/15 text-[10px] font-semibold text-primary">
                        P
                    </span>
                    <span className="truncate text-sm font-semibold">Playground</span>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="h-7 w-7 shrink-0"
                    onClick={() => onCollapsedChange?.(true)}
                    title="Collapse sidebar"
                >
                    <PanelLeftClose className="h-4 w-4" />
                </Button>
            </div>

            <div className="space-y-2 border-b border-border p-3">
                <div className="relative">
                    <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Search sessions…"
                        className="h-8 pl-8 text-xs"
                    />
                </div>
                <div className="flex items-center justify-between">
                    <span className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Chat</span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-7 w-7"
                        onClick={onNewThread}
                        disabled={disabled}
                        title="New chat"
                    >
                        <Plus className="h-4 w-4" />
                    </Button>
                </div>
            </div>

            <ScrollArea className="flex-1">
                <div className="space-y-0.5 p-2">
                    {loading && <p className="px-2 py-3 text-xs text-muted-foreground">Loading sessions…</p>}
                    {!loading && filtered.length === 0 && (
                        <p className="px-2 py-3 text-xs text-muted-foreground">
                            {query ? 'No matching sessions.' : 'No sessions yet.'}
                        </p>
                    )}
                    {filtered.map((thread) => {
                        const active = thread.id === activeThreadId;

                        return (
                            <div
                                key={thread.id}
                                className={cn(
                                    'group flex items-center gap-1 rounded-md px-2 py-2 text-left transition-colors',
                                    active ? 'bg-accent text-accent-foreground' : 'hover:bg-muted/60',
                                )}
                            >
                                <button
                                    type="button"
                                    className="min-w-0 flex-1 text-left"
                                    onClick={() => onSelectThread?.(thread.id)}
                                    disabled={disabled}
                                >
                                    <span className="block truncate text-sm font-medium">{thread.label}</span>
                                    {thread.preview && thread.preview !== thread.label && (
                                        <span className="mt-0.5 block truncate text-[11px] text-muted-foreground">
                                            {thread.preview}
                                        </span>
                                    )}
                                </button>
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className={cn(
                                                'h-7 w-7 shrink-0 opacity-0 group-hover:opacity-100 data-[state=open]:opacity-100',
                                                active && 'opacity-100',
                                            )}
                                            title="Options"
                                        >
                                            <MoreHorizontal className="h-3.5 w-3.5" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" className="w-44">
                                        <DropdownMenuItem onClick={() => handleCopy(thread.id)}>
                                            {copiedId === thread.id ? (
                                                <Check className="mr-2 h-3.5 w-3.5" />
                                            ) : (
                                                <Copy className="mr-2 h-3.5 w-3.5" />
                                            )}
                                            Copy thread ID
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        );
                    })}
                </div>
            </ScrollArea>
        </aside>
    );
}
