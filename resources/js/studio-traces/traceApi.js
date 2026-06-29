function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export async function fetchTraces(tracesIndexUrl, { page = 1, perPage = 25 } = {}) {
    const url = new URL(tracesIndexUrl, window.location.origin);
    url.searchParams.set('page', String(page));
    url.searchParams.set('per_page', String(perPage));

    const response = await fetch(url.toString(), {
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error('Failed to load traces.');
    }

    return response.json();
}

export async function fetchTrace(traceJsonUrl) {
    const response = await fetch(traceJsonUrl, {
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error('Failed to load trace.');
    }

    return response.json();
}

export function resolveTraceUrl(template, traceId) {
    return template.replace('__TRACE__', String(traceId));
}
