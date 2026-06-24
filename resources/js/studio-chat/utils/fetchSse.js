/**
 * POST (or GET) request that parses Server-Sent Events from the response body.
 *
 * @param {string} url
 * @param {RequestInit} options
 * @yields {{ event: string, data: unknown }}
 */
export async function* fetchSse(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        headers: {
            Accept: 'text/event-stream',
            ...(options.headers || {}),
        },
    });

    if (!response.ok) {
        const text = await response.text();
        throw new Error(text || `Request failed (${response.status})`);
    }

    const reader = response.body?.getReader();
    if (!reader) {
        throw new Error('Streaming not supported.');
    }

    const decoder = new TextDecoder();
    let buffer = '';
    let eventName = 'message';
    let dataLines = [];

    const flush = () => {
        if (dataLines.length === 0) {
            return null;
        }

        const raw = dataLines.join('\n');
        dataLines = [];
        const name = eventName;
        eventName = 'message';

        try {
            return { event: name, data: JSON.parse(raw) };
        } catch {
            return { event: name, data: raw };
        }
    };

    while (true) {
        const { done, value } = await reader.read();
        if (done) {
            break;
        }

        buffer += decoder.decode(value, { stream: true });
        const lines = buffer.split('\n');
        buffer = lines.pop() ?? '';

        for (const line of lines) {
            if (line === '') {
                const payload = flush();
                if (payload) {
                    yield payload;
                }
                continue;
            }

            if (line.startsWith('event:')) {
                eventName = line.slice(6).trim();
            } else if (line.startsWith('data:')) {
                dataLines.push(line.slice(5).trim());
            }
        }
    }

    const payload = flush();
    if (payload) {
        yield payload;
    }
}

export function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export function jsonPostOptions(body) {
    return {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            Accept: 'text/event-stream',
        },
        body: JSON.stringify(body),
    };
}
