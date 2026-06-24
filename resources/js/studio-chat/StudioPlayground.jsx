import { useCallback, useMemo, useState } from 'react';
import { loadPresets, presetStorageKey, savePresets } from './utils/presets';
import { createId } from './utils/id';

export default function StudioPlayground({
    mode,
    entityId,
    context,
    onContextChange,
    collapsed: initialCollapsed = false,
}) {
    const storageKey = useMemo(() => presetStorageKey(mode, entityId), [mode, entityId]);
    const [collapsed, setCollapsed] = useState(initialCollapsed);
    const [contextJson, setContextJson] = useState(() => JSON.stringify(context ?? {}, null, 2));
    const [jsonError, setJsonError] = useState('');
    const [presets, setPresets] = useState(() => loadPresets(storageKey));
    const [presetName, setPresetName] = useState('');

    const applyContext = useCallback(
        (value) => {
            setContextJson(value);
            try {
                const parsed = JSON.parse(value || '{}');
                setJsonError('');
                onContextChange?.(parsed);
            } catch {
                setJsonError('Invalid JSON');
            }
        },
        [onContextChange],
    );

    const savePreset = () => {
        if (!presetName.trim()) {
            return;
        }

        const next = [
            ...presets.filter((item) => item.name !== presetName.trim()),
            { name: presetName.trim(), context: contextJson },
        ];

        setPresets(next);
        savePresets(storageKey, next);
        setPresetName('');
    };

    const loadPreset = (preset) => {
        applyContext(preset.context);
    };

    return (
        <div className={`ab-playground${collapsed ? ' ab-playground--collapsed' : ''}`}>
            <button type="button" className="ab-playground-toggle" onClick={() => setCollapsed((value) => !value)}>
                Playground {collapsed ? '▸' : '▾'}
            </button>
            {!collapsed && (
                <div className="ab-playground-body">
                    <label className="ab-playground-label">
                        {mode === 'workflow' ? 'Initial state JSON' : 'Context JSON'}
                    </label>
                    <textarea
                        className="ab-input ab-playground-json"
                        rows={6}
                        value={contextJson}
                        onChange={(event) => applyContext(event.target.value)}
                    />
                    {jsonError && <p className="ab-error ab-playground-error">{jsonError}</p>}

                    <div className="ab-playground-presets">
                        <input
                            className="ab-input"
                            placeholder="Preset name"
                            value={presetName}
                            onChange={(event) => setPresetName(event.target.value)}
                        />
                        <button type="button" className="ab-btn ab-btn-sm" onClick={savePreset}>
                            Save preset
                        </button>
                    </div>
                    {presets.length > 0 && (
                        <div className="ab-playground-preset-list">
                            {presets.map((preset) => (
                                <button
                                    key={preset.name}
                                    type="button"
                                    className="ab-btn ab-btn-sm"
                                    onClick={() => loadPreset(preset)}
                                >
                                    {preset.name}
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

export { createId };
