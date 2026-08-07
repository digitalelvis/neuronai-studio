import { useCallback, useEffect, useRef, useState } from 'react';
import { X } from 'lucide-react';
import { cn } from '@/lib/utils';

const VARIANT_CLASSES = {
    default: 'border-border bg-card text-foreground',
    info: 'border-border bg-card text-foreground',
    success: 'border-green-500/30 bg-green-500/10 text-green-400',
    error: 'border-destructive/30 bg-destructive/10 text-red-400',
    warning: 'border-amber-500/30 bg-amber-500/10 text-amber-400',
};

const DEFAULT_DURATIONS = {
    default: 4000,
    info: 4000,
    success: 3000,
    error: 6000,
    warning: 5000,
};

let toastId = 0;

export function createToastId() {
    toastId += 1;

    return `studio-toast-${toastId}`;
}

function ToastItem({ toast, onDismiss }) {
    const variant = VARIANT_CLASSES[toast.variant] ? toast.variant : 'default';
    const isError = variant === 'error';

    useEffect(() => {
        if (!toast.duration || toast.duration <= 0) {
            return undefined;
        }

        const timer = window.setTimeout(() => onDismiss(toast.id), toast.duration);

        return () => window.clearTimeout(timer);
    }, [toast.duration, toast.id, onDismiss]);

    return (
        <div
            className={cn(
                'pointer-events-auto flex w-full max-w-md items-start gap-3 rounded-lg border px-4 py-3 text-sm shadow-lg',
                VARIANT_CLASSES[variant],
            )}
            role={isError ? 'alert' : 'status'}
            aria-live={isError ? 'assertive' : 'polite'}
        >
            <p className="min-w-0 flex-1 whitespace-pre-wrap wrap-break-word">{toast.message}</p>
            {isError && (
                <button
                    type="button"
                    className="shrink-0 rounded-md p-0.5 opacity-70 transition-opacity hover:opacity-100"
                    aria-label="Dismiss"
                    onClick={() => onDismiss(toast.id)}
                >
                    <X className="h-3.5 w-3.5" />
                </button>
            )}
        </div>
    );
}

export function Toaster({ toasts, onDismiss }) {
    if (toasts.length === 0) {
        return null;
    }

    return (
        <div
            className="pointer-events-none fixed bottom-6 left-1/2 z-200 flex w-[min(28rem,calc(100vw-2rem))] -translate-x-1/2 flex-col-reverse gap-2"
            aria-label="Notifications"
        >
            {toasts.map((toast) => (
                <ToastItem key={toast.id} toast={toast} onDismiss={onDismiss} />
            ))}
        </div>
    );
}

export function useToastStore() {
    const [toasts, setToasts] = useState([]);
    const recentRef = useRef(new Map());

    const dismiss = useCallback((id) => {
        setToasts((current) => current.filter((toast) => toast.id !== id));
    }, []);

    const show = useCallback((options = {}) => {
        const message = typeof options === 'string' ? options : String(options.message ?? '');
        if (!message.trim()) {
            return null;
        }

        const variant = typeof options === 'string' ? 'default' : (options.variant ?? 'default');
        const resolvedVariant = VARIANT_CLASSES[variant] ? variant : 'default';
        const trimmed = message.trim();
        const dedupeKey = `${resolvedVariant}:${trimmed}`;
        const now = Date.now();
        const lastShown = recentRef.current.get(dedupeKey) ?? 0;

        // Guard against Livewire/window double-delivery of the same event.
        if (now - lastShown < 750) {
            return null;
        }

        recentRef.current.set(dedupeKey, now);

        const duration =
            typeof options === 'object' && typeof options.duration === 'number'
                ? options.duration
                : DEFAULT_DURATIONS[resolvedVariant] ?? DEFAULT_DURATIONS.default;

        const id =
            typeof options === 'object' && options.id ? String(options.id) : createToastId();

        setToasts((current) => [
            ...current.slice(-4),
            {
                id,
                message: trimmed,
                variant: resolvedVariant,
                duration,
            },
        ]);

        return id;
    }, []);

    return { toasts, show, dismiss };
}
