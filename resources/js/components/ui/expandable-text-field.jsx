import * as React from 'react';
import { FileText, Maximize } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

const ExpandableTextField = React.forwardRef(function ExpandableTextField(
    {
        as = 'textarea',
        value,
        onChange,
        disabled = false,
        readOnly = false,
        className,
        rows,
        placeholder,
        label = 'Edit text content',
        id,
        name,
        ...props
    },
    ref,
) {
    const [open, setOpen] = React.useState(false);
    const [draft, setDraft] = React.useState(value ?? '');
    const canExpand = !disabled && !readOnly;
    const isMono = typeof className === 'string' && className.includes('font-mono');

    const openEditor = (event) => {
        event.preventDefault();
        event.stopPropagation();
        setDraft(value ?? '');
        setOpen(true);
    };

    const handleOpenChange = (next) => {
        if (!next) {
            setDraft(value ?? '');
        }
        setOpen(next);
    };

    const finishEditing = () => {
        const nextValue = draft ?? '';
        if (nextValue !== (value ?? '') && typeof onChange === 'function') {
            onChange({ target: { value: nextValue, name, id } });
        }
        setOpen(false);
    };

    const Field = as === 'input' ? Input : Textarea;
    const fieldProps =
        as === 'input'
            ? { type: 'text', ...props }
            : { rows: rows ?? 3, ...props };

    return (
        <>
            <div className="relative">
                <Field
                    ref={ref}
                    id={id}
                    name={name}
                    value={value ?? ''}
                    onChange={onChange}
                    disabled={disabled}
                    readOnly={readOnly}
                    placeholder={placeholder}
                    className={cn(canExpand && 'pr-9', className)}
                    {...fieldProps}
                />
                {canExpand && (
                    <button
                        type="button"
                        onClick={openEditor}
                        className={cn(
                            'absolute right-1.5 inline-flex h-6 w-6 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                            as === 'input' ? 'top-1/2 -translate-y-1/2' : 'top-1.5',
                        )}
                        title="Expand editor"
                        aria-label="Expand editor"
                    >
                        <Maximize className="h-3.5 w-3.5" />
                    </button>
                )}
            </div>

            <Dialog open={open} onOpenChange={handleOpenChange}>
                <DialogContent className="flex h-[100dvh] w-screen max-w-none flex-col gap-4 rounded-none border-0 sm:rounded-none">
                    <DialogHeader className="shrink-0">
                        <DialogTitle className="flex items-center gap-2 text-base">
                            <FileText className="h-4 w-4 text-muted-foreground" />
                            {label}
                        </DialogTitle>
                    </DialogHeader>
                    <Textarea
                        value={draft}
                        onChange={(e) => setDraft(e.target.value)}
                        placeholder={placeholder}
                        className={cn(
                            'min-h-0 flex-1 resize-none text-sm',
                            isMono ? 'font-mono text-xs' : 'font-mono',
                        )}
                        autoFocus
                    />
                    <DialogFooter className="shrink-0">
                        <Button type="button" onClick={finishEditing}>
                            Finish Editing
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
});

ExpandableTextField.displayName = 'ExpandableTextField';

export { ExpandableTextField };
