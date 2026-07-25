import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

/**
 * Near-fullscreen dialog shell for the workflow playground.
 * Page layouts (agent) should render children directly without this wrapper.
 */
export default function PlaygroundShell({ open, onOpenChange, children, className }) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                hideCloseButton
                className={cn(
                    'flex h-[min(96vh,960px)] w-[min(96vw,1400px)] max-w-none translate-x-[-50%] translate-y-[-50%] flex-col gap-0 overflow-hidden rounded-xl border bg-background p-0 shadow-2xl',
                    className,
                )}
            >
                <DialogTitle className="sr-only">Playground</DialogTitle>
                <DialogDescription className="sr-only">
                    Test your agent or workflow with chat sessions and traces.
                </DialogDescription>
                <div className="flex min-h-0 flex-1 flex-col overflow-hidden">{children}</div>
            </DialogContent>
        </Dialog>
    );
}
