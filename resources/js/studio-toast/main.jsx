import { createRoot } from 'react-dom/client';
import { useEffect } from 'react';
import { Toaster, useToastStore } from './Toast';
import '../../css/globals.css';

function normalizeToastPayload(payload) {
    if (payload == null) {
        return null;
    }

    if (typeof payload === 'string') {
        return { variant: 'default', message: payload };
    }

    if (Array.isArray(payload)) {
        const first = payload[0];
        if (first && typeof first === 'object') {
            return normalizeToastPayload(first);
        }

        return null;
    }

    if (typeof payload === 'object') {
        const message = payload.message ?? payload.text ?? '';
        const variant = payload.variant ?? payload.type ?? 'default';

        if (!message) {
            return null;
        }

        return {
            variant,
            message: String(message),
            duration: typeof payload.duration === 'number' ? payload.duration : undefined,
        };
    }

    return null;
}

function StudioToastApp({ showRef }) {
    const { toasts, show, dismiss } = useToastStore();

    useEffect(() => {
        showRef.current = show;
    }, [show, showRef]);

    useEffect(() => {
        // Single channel: Livewire `$this->dispatch('studio-toast')` also emits a
        // window CustomEvent, so do not also subscribe via Livewire.on (would double-fire).
        const onWindowToast = (event) => {
            const normalized = normalizeToastPayload(event.detail);
            if (normalized) {
                show(normalized);
            }
        };

        window.addEventListener('studio-toast', onWindowToast);

        return () => window.removeEventListener('studio-toast', onWindowToast);
    }, [show]);

    useEffect(() => {
        const pending = Array.isArray(window.__STUDIO_FLASH_TOASTS__)
            ? window.__STUDIO_FLASH_TOASTS__
            : [];

        pending.forEach((item) => {
            const normalized = normalizeToastPayload(item);
            if (normalized) {
                show(normalized);
            }
        });

        window.__STUDIO_FLASH_TOASTS__ = [];
    }, [show]);

    return <Toaster toasts={toasts} onDismiss={dismiss} />;
}

function ensureRoot() {
    let root = document.getElementById('studio-toast-root');
    if (!root) {
        root = document.createElement('div');
        root.id = 'studio-toast-root';
        document.body.appendChild(root);
    }

    return root;
}

const showRef = { current: null };

function showToast(options) {
    if (showRef.current) {
        return showRef.current(options);
    }

    window.dispatchEvent(
        new CustomEvent('studio-toast', {
            detail: typeof options === 'string' ? { message: options } : options,
        }),
    );

    return null;
}

window.NeuronAIStudioToast = {
    show: showToast,
    success: (message, options = {}) => showToast({ ...options, variant: 'success', message }),
    error: (message, options = {}) => showToast({ ...options, variant: 'error', message }),
    info: (message, options = {}) => showToast({ ...options, variant: 'info', message }),
    warning: (message, options = {}) => showToast({ ...options, variant: 'warning', message }),
};

const reactRoot = createRoot(ensureRoot());
reactRoot.render(<StudioToastApp showRef={showRef} />);

export default window.NeuronAIStudioToast;
