import { useEffect, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Globe, X } from 'lucide-react';

/**
 * Literal vs Studio variable binder. Bound values use wire format `var:NAME`.
 */
export default function VariableInput({
    value = '',
    onChange,
    variables: initialVariables = [],
    sensitive = false,
    placeholder = '',
    hint = '',
    disabled = false,
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [reveal, setReveal] = useState(false);
    const [variables, setVariables] = useState(initialVariables);

    useEffect(() => {
        setVariables(initialVariables);
    }, [initialVariables]);

    useEffect(() => {
        const onCreated = (event) => {
            if (disabled) return;
            const detail = event.detail || {};
            const name = detail.name;
            const type = detail.type || 'credential';
            if (!name) return;
            setVariables((current) => {
                if (current.some((v) => v.name === name)) return current;
                return [...current, { name, type }].sort((a, b) => a.name.localeCompare(b.name));
            });
            onChange(`var:${name}`);
            setOpen(false);
        };
        window.addEventListener('studio-variable-created', onCreated);
        return () => window.removeEventListener('studio-variable-created', onCreated);
    }, [onChange, disabled]);

    const boundName = value?.startsWith('var:') ? value.slice(4) : null;
    const filtered = useMemo(() => {
        const needle = query.toLowerCase();
        if (!needle) return variables;
        return variables.filter((v) => String(v.name).toLowerCase().includes(needle));
    }, [variables, query]);

    const bind = (name) => {
        if (disabled) return;
        onChange(`var:${name}`);
        setOpen(false);
        setQuery('');
    };

    const clearBind = () => {
        if (disabled) return;
        if (boundName) onChange('');
    };

    const openCreate = () => {
        if (disabled) return;
        setOpen(false);
        if (window.Livewire?.dispatch) {
            window.Livewire.dispatch('studio-open-create-variable');
        }
    };

    return (
        <div className="space-y-1.5">
            <div className="flex gap-2">
                <div className="relative min-w-0 flex-1">
                    {boundName ? (
                        <div className="flex h-9 items-center justify-between rounded-md border border-border bg-muted/40 px-3 text-sm">
                            <code className="truncate">var:{boundName}</code>
                            {!disabled && (
                                <button type="button" className="text-muted-foreground hover:text-foreground" onClick={clearBind} title="Clear binding">
                                    <X className="h-3.5 w-3.5" />
                                </button>
                            )}
                        </div>
                    ) : (
                        <div className="relative">
                            <Input
                                type={sensitive && !reveal ? 'password' : 'text'}
                                value={value}
                                onChange={(e) => onChange(e.target.value)}
                                placeholder={placeholder}
                                disabled={disabled}
                            />
                            {sensitive && !disabled && (
                                <button
                                    type="button"
                                    className="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] uppercase text-muted-foreground"
                                    onClick={() => setReveal((v) => !v)}
                                >
                                    {reveal ? 'Hide' : 'Show'}
                                </button>
                            )}
                        </div>
                    )}
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="shrink-0"
                    onClick={() => setOpen((v) => !v)}
                    title="Bind Studio variable"
                    disabled={disabled}
                >
                    <Globe className="h-4 w-4" />
                </Button>
            </div>
            {open && !disabled && (
                <div className="rounded-md border border-border bg-background shadow-md">
                    <div className="border-b border-border p-2">
                        <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Search variables..." />
                    </div>
                    <ul className="max-h-48 overflow-y-auto py-1 text-sm">
                        {filtered.map((v) => (
                            <li key={v.name}>
                                <button
                                    type="button"
                                    className="flex w-full items-center justify-between px-3 py-1.5 text-left hover:bg-muted"
                                    onClick={() => bind(v.name)}
                                >
                                    <code>{v.name}</code>
                                    <span className="text-xs text-muted-foreground">{v.type}</span>
                                </button>
                            </li>
                        ))}
                        {filtered.length === 0 && (
                            <li className="px-3 py-2 text-muted-foreground">No variables found</li>
                        )}
                    </ul>
                    <div className="flex items-center justify-between gap-2 border-t border-border p-2">
                        <button type="button" className="text-xs text-muted-foreground hover:text-foreground" onClick={clearBind}>
                            Clear binding
                        </button>
                        <button type="button" className="text-xs font-medium text-primary hover:underline" onClick={openCreate}>
                            + Add variable
                        </button>
                    </div>
                </div>
            )}
            {hint ? <p className="text-xs text-muted-foreground">{hint}</p> : null}
            {boundName ? <Label className="text-xs text-muted-foreground">Bound to Studio variable (secret not shown)</Label> : null}
        </div>
    );
}
