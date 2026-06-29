import { createRoot } from 'react-dom/client';
import LivewireCodeEditor from '@/components/code/LivewireCodeEditor';
import CodeViewer from '@/components/code/CodeViewer';
import '../../css/globals.css';

const roots = new WeakMap();

function readOptions(el, overrides = {}) {
    const preset = window.__NEURONAI_CODE_EDITORS?.[el.id];

    return {
        wireId: overrides.wireId ?? el.dataset.wireId ?? '',
        field: overrides.field ?? el.dataset.field ?? '',
        initialValue: overrides.value ?? preset?.value ?? el.dataset.value ?? '',
        language: overrides.language ?? el.dataset.language ?? 'json',
        minHeight: overrides.minHeight ?? el.dataset.minHeight ?? '384px',
        readOnly: overrides.readOnly ?? el.dataset.readOnly === 'true',
        showFormat: overrides.showFormat ?? el.dataset.showFormat !== 'false',
    };
}

export function mountEditor(el, options = {}) {
    if (!el) {
        return null;
    }

    const config = readOptions(el, options);
    let root = roots.get(el);

    if (!root) {
        root = createRoot(el);
        roots.set(el, root);
    }

    root.render(
        <LivewireCodeEditor
            wireId={config.wireId}
            field={config.field}
            initialValue={config.initialValue}
            language={config.language}
            minHeight={config.minHeight}
            showFormat={config.showFormat}
        />,
    );

    return root;
}

export function mountViewer(el, options = {}) {
    if (!el) {
        return null;
    }

    const config = readOptions(el, options);
    let root = roots.get(el);

    if (!root) {
        root = createRoot(el);
        roots.set(el, root);
    }

    root.render(
        <CodeViewer
            value={config.initialValue}
            language={config.language}
            minHeight={config.minHeight}
        />,
    );

    return root;
}

function mountAllEditors() {
    document.querySelectorAll('[data-neuron-code-editor]').forEach((el) => {
        if (el.dataset.readOnly === 'true') {
            mountViewer(el);
        } else {
            mountEditor(el);
        }
    });
}

window.NeuronStudioCode = {
    mountEditor,
    mountViewer,
};

document.addEventListener('DOMContentLoaded', mountAllEditors);
document.addEventListener('livewire:navigated', mountAllEditors);
