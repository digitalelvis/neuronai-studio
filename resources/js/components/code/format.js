export function formatJson(text) {
    const parsed = JSON.parse(text);

    return JSON.stringify(parsed, null, 2);
}

export function tryFormatJson(text) {
    try {
        return { ok: true, value: formatJson(text) };
    } catch (error) {
        return { ok: false, error: error instanceof Error ? error.message : 'Invalid JSON' };
    }
}
