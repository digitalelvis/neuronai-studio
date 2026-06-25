export function createThreadId() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `thread-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

export function getThreadFromUrl() {
    return new URLSearchParams(window.location.search).get('thread');
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
