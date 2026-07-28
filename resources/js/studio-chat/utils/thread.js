/**
 * RFC 4122 UUID v4. Prefer crypto.randomUUID when available (secure contexts);
 * fall back to getRandomValues / Math.random so HTTP playgrounds still work.
 */
export function createThreadId() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        try {
            return crypto.randomUUID();
        } catch {
            // Insecure contexts may expose the method but throw when called.
        }
    }

    if (typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function') {
        const bytes = new Uint8Array(16);
        crypto.getRandomValues(bytes);
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;

        const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');

        return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
    }

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
        const random = (Math.random() * 16) | 0;
        const value = char === 'x' ? random : (random & 0x3) | 0x8;

        return value.toString(16);
    });
}

const UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

export function isThreadId(value) {
    return typeof value === 'string' && UUID_RE.test(value);
}

export function getThreadFromUrl() {
    const value = new URLSearchParams(window.location.search).get('thread');

    return isThreadId(value) ? value : null;
}

export function setThreadInUrl(threadId) {
    const url = new URL(window.location.href);

    if (threadId) {
        url.searchParams.set('thread', threadId);
    } else {
        url.searchParams.delete('thread');
    }

    window.history.replaceState({}, '', url);
}
