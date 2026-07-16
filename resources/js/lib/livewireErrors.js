/**
 * Normalize validation messages into field → string[] map.
 *
 * @param {unknown} raw
 * @returns {Record<string, string[]>}
 */
function normalizeErrorMap(raw) {
    if (!raw || typeof raw !== 'object') {
        return {};
    }

    /** @type {Record<string, string[]>} */
    const normalized = {};

    for (const [field, messages] of Object.entries(raw)) {
        if (Array.isArray(messages)) {
            normalized[field] = messages.map(String);
        } else if (typeof messages === 'string') {
            normalized[field] = [messages];
        }
    }

    return normalized;
}

/**
 * Collect Livewire validation errors for a component.
 * Prefers Livewire 4 `$errors.all()`; falls back to LW3 snapshot memo.
 *
 * @param {string} wireId
 * @returns {Record<string, string[]>}
 */
export function collectLivewireErrors(wireId) {
    const wire = window.Livewire?.find(wireId);

    if (!wire) {
        return {};
    }

    const errorsBag = wire.$errors;

    if (errorsBag && typeof errorsBag.all === 'function') {
        return normalizeErrorMap(errorsBag.all());
    }

    const snapshotErrors =
        wire.__instance?.snapshot?.memo?.errors ??
        wire.__instance?.snapshot?.errors;

    return normalizeErrorMap(snapshotErrors);
}

/** @param {Record<string, string[]>} errors */
export function formatLivewireErrorSummary(errors) {
    const messages = [];

    for (const fieldMessages of Object.values(errors)) {
        if (Array.isArray(fieldMessages)) {
            messages.push(...fieldMessages);
        }
    }

    return messages.join(' ');
}

/** @param {Record<string, string[]>} errors */
export function fieldError(errors, field) {
    const messages = errors[field];

    return Array.isArray(messages) && messages.length > 0 ? messages[0] : '';
}
