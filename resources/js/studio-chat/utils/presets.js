export function loadPresets(storageKey) {
    try {
        const raw = localStorage.getItem(storageKey);
        return raw ? JSON.parse(raw) : [];
    } catch {
        return [];
    }
}

export function savePresets(storageKey, presets) {
    localStorage.setItem(storageKey, JSON.stringify(presets));
}

export function presetStorageKey(mode, entityId) {
    return `neuronai-studio:${mode}:${entityId}:presets`;
}
