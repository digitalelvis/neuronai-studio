export function loadPresets(storageKey) {
    if (!storageKey || typeof localStorage === 'undefined') {
        return [];
    }

    try {
        const raw = localStorage.getItem(storageKey);
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

export function savePresets(storageKey, presets) {
    if (!storageKey || typeof localStorage === 'undefined') {
        return;
    }

    try {
        localStorage.setItem(storageKey, JSON.stringify(presets));
        window.dispatchEvent(
            new CustomEvent('playground-presets-changed', { detail: { storageKey } }),
        );
    } catch {
        // Ignore quota / private mode errors.
    }
}

/**
 * @param {string} storageKey
 * @param {{ name: string, context: string }} preset
 * @returns {array}
 */
export function upsertPreset(storageKey, preset) {
    const name = typeof preset?.name === 'string' ? preset.name.trim() : '';
    if (!name || !storageKey) {
        return loadPresets(storageKey);
    }

    const next = [
        ...loadPresets(storageKey).filter((item) => item.name !== name),
        { name, context: typeof preset.context === 'string' ? preset.context : '{}' },
    ];

    savePresets(storageKey, next);
    return next;
}

export function deletePreset(storageKey, name) {
    if (!storageKey || !name) {
        return loadPresets(storageKey);
    }

    const next = loadPresets(storageKey).filter((item) => item.name !== name);
    savePresets(storageKey, next);
    return next;
}

export function presetStorageKey(mode, entityId) {
    return `neuronai-studio:${mode}:${entityId}:presets`;
}

export function contextStorageKey(mode, entityId) {
    return `neuronai-studio:${mode}:${entityId}:context`;
}

/**
 * @returns {Record<string, unknown>|null}
 */
export function loadLastContext(mode, entityId) {
    if (!mode || !entityId || typeof localStorage === 'undefined') {
        return null;
    }

    try {
        const raw = localStorage.getItem(contextStorageKey(mode, entityId));
        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : null;
    } catch {
        return null;
    }
}

/**
 * @param {string} mode
 * @param {string} entityId
 * @param {Record<string, unknown>} context
 */
export function saveLastContext(mode, entityId, context) {
    if (!mode || !entityId || typeof localStorage === 'undefined') {
        return;
    }

    if (!context || typeof context !== 'object' || Array.isArray(context)) {
        return;
    }

    try {
        localStorage.setItem(contextStorageKey(mode, entityId), JSON.stringify(context));
    } catch {
        // Ignore quota / private mode errors.
    }
}
