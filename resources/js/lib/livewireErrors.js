/** @returns {Record<string, string[]>} */
export function collectLivewireErrors(wireId) {
    const wire = window.Livewire?.find(wireId);
    const errors = wire?.__instance?.snapshot?.memo?.errors;

    return errors && typeof errors === 'object' ? errors : {};
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
