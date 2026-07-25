import * as React from 'react';
import { Check, ChevronsUpDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';

/**
 * Searchable single-select combobox.
 *
 * @param {{
 *   options: Array<{ value: string, label: string }>,
 *   value?: string,
 *   onValueChange?: (value: string) => void,
 *   placeholder?: string,
 *   searchPlaceholder?: string,
 *   emptyText?: string,
 *   disabled?: boolean,
 *   className?: string,
 * }} props
 */
export function Combobox({
    options = [],
    value = '',
    onValueChange,
    placeholder = 'Select…',
    searchPlaceholder = 'Search…',
    emptyText = 'No results.',
    disabled = false,
    className,
}) {
    const [open, setOpen] = React.useState(false);
    const [query, setQuery] = React.useState('');
    const inputRef = React.useRef(null);

    const selected = options.find((option) => option.value === value);
    const normalizedQuery = query.trim().toLowerCase();
    const filtered = normalizedQuery
        ? options.filter(
              (option) =>
                  option.label.toLowerCase().includes(normalizedQuery) ||
                  option.value.toLowerCase().includes(normalizedQuery),
          )
        : options;

    React.useEffect(() => {
        if (open) {
            const timer = window.setTimeout(() => inputRef.current?.focus(), 0);
            return () => window.clearTimeout(timer);
        }

        setQuery('');
        return undefined;
    }, [open]);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    disabled={disabled}
                    className={cn('h-9 w-full justify-between font-normal', !selected && 'text-muted-foreground', className)}
                >
                    <span className="truncate">{selected?.label ?? placeholder}</span>
                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-[var(--radix-popover-trigger-width)] p-0" align="start">
                <div className="border-b border-border p-2">
                    <Input
                        ref={inputRef}
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder={searchPlaceholder}
                        className="h-8"
                    />
                </div>
                <div className="max-h-56 overflow-y-auto p-1">
                    {filtered.length === 0 ? (
                        <p className="px-2 py-3 text-center text-xs text-muted-foreground">{emptyText}</p>
                    ) : (
                        filtered.map((option) => {
                            const isSelected = option.value === value;

                            return (
                                <button
                                    key={option.value}
                                    type="button"
                                    className={cn(
                                        'relative flex w-full cursor-default select-none items-center rounded-sm py-1.5 pl-2 pr-8 text-left text-sm outline-none hover:bg-accent hover:text-accent-foreground',
                                        isSelected && 'bg-accent/60',
                                    )}
                                    onClick={() => {
                                        onValueChange?.(option.value);
                                        setOpen(false);
                                    }}
                                >
                                    <span className="truncate">{option.label}</span>
                                    {isSelected && (
                                        <span className="absolute right-2 flex h-3.5 w-3.5 items-center justify-center">
                                            <Check className="h-4 w-4" />
                                        </span>
                                    )}
                                </button>
                            );
                        })
                    )}
                </div>
            </PopoverContent>
        </Popover>
    );
}
