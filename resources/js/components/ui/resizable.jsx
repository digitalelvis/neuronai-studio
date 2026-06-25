import { Group, Panel, Separator } from 'react-resizable-panels';
import { cn } from '@/lib/utils';

function ResizablePanelGroup({ className, direction = 'horizontal', orientation, ...props }) {
    return (
        <Group
            orientation={orientation ?? direction}
            className={cn('flex h-full w-full min-h-0 aria-[orientation=vertical]:flex-col', className)}
            {...props}
        />
    );
}

function ResizablePanel({ className, ...props }) {
    return <Panel className={cn('min-h-0 min-w-0', className)} {...props} />;
}

function ResizableHandle({ withHandle, className, ...props }) {
    return (
        <Separator
            className={cn(
                'relative z-20 flex w-px shrink-0 items-center justify-center bg-border',
                'after:absolute after:inset-y-0 after:left-1/2 after:w-1 after:-translate-x-1/2',
                'focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring',
                'aria-[orientation=vertical]:h-px aria-[orientation=vertical]:w-full',
                'aria-[orientation=vertical]:after:left-0 aria-[orientation=vertical]:after:h-1 aria-[orientation=vertical]:after:w-full',
                'aria-[orientation=vertical]:after:translate-x-0 aria-[orientation=vertical]:after:-translate-y-1/2',
                '[&[aria-orientation=vertical]>div]:rotate-90',
                className,
            )}
            {...props}
        >
            {withHandle && (
                <div className="z-10 flex h-4 w-3 items-center justify-center rounded-sm border bg-border">
                    <svg width="15" height="15" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg" className="h-2.5 w-2.5">
                        <path
                            d="M5.5 4.625C6.12132 4.625 6.625 4.12132 6.625 3.5C6.625 2.87868 6.12132 2.375 5.5 2.375C4.87868 2.375 4.375 2.87868 4.375 3.5C4.375 4.12132 4.87868 4.625 5.5 4.625ZM9.5 4.625C10.1213 4.625 10.625 4.12132 10.625 3.5C10.625 2.87868 10.1213 2.375 9.5 2.375C8.87868 2.375 8.375 2.87868 8.375 3.5C8.375 4.12132 8.87868 4.625 9.5 4.625ZM5.5 8.125C6.12132 8.125 6.625 7.62132 6.625 7C6.625 6.37868 6.12132 5.875 5.5 5.875C4.87868 5.875 4.375 6.37868 4.375 7C4.375 7.62132 4.87868 8.125 5.5 8.125ZM9.5 8.125C10.1213 8.125 10.625 7.62132 10.625 7C10.625 6.37868 10.1213 5.875 9.5 5.875C8.87868 5.875 8.375 6.37868 8.375 7C8.375 7.62132 8.87868 8.125 9.5 8.125ZM5.5 11.625C6.12132 11.625 6.625 11.1213 6.625 10.5C6.625 9.87868 6.12132 9.375 5.5 9.375C4.87868 9.375 4.375 9.87868 4.375 10.5C4.375 11.1213 4.87868 11.625 5.5 11.625ZM9.5 11.625C10.1213 11.625 10.625 11.1213 10.625 10.5C10.625 9.87868 10.1213 9.375 9.5 9.375C8.87868 9.375 8.375 9.87868 8.375 10.5C8.375 11.1213 8.87868 11.625 9.5 11.625Z"
                            fill="currentColor"
                            fillRule="evenodd"
                            clipRule="evenodd"
                        />
                    </svg>
                </div>
            )}
        </Separator>
    );
}

export { ResizablePanelGroup, ResizablePanel, ResizableHandle };
