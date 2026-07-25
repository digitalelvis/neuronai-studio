import { useCallback } from 'react';
import { NodeToolbar, Position } from '@xyflow/react';
import { Copy, Trash2 } from 'lucide-react';
import { useCanvasUi } from '../CanvasUiContext';

export default function StickyNote({ id, data, selected }) {
    const { readOnly } = useCanvasUi();
    const text = data.config?.text ?? data.config?.content ?? '';

    const updateText = useCallback(
        (value) => {
            window.dispatchEvent(
                new CustomEvent('canvas-node-updated', {
                    detail: { id, data: { text: value } },
                }),
            );
        },
        [id],
    );

    const duplicate = (event) => {
        event.stopPropagation();
        window.dispatchEvent(new CustomEvent('canvas-duplicate-node', { detail: { id } }));
    };

    const remove = (event) => {
        event.stopPropagation();
        window.dispatchEvent(new CustomEvent('canvas-remove-node', { detail: { id } }));
    };

    return (
        <div className={`ab-sticky-note${selected ? ' selected' : ''}`}>
            {!readOnly && (
                <NodeToolbar isVisible={selected} position={Position.Top} offset={8}>
                    <div className="ab-flow-node-toolbar">
                        <button type="button" className="ab-flow-node-toolbar-btn" onClick={duplicate} title="Duplicate">
                            <Copy className="h-3.5 w-3.5" />
                        </button>
                        <button type="button" className="ab-flow-node-toolbar-btn" onClick={remove} title="Delete">
                            <Trash2 className="h-3.5 w-3.5" />
                        </button>
                    </div>
                </NodeToolbar>
            )}
            <div className="ab-sticky-note-title">Note</div>
            <textarea
                className="nodrag nowheel ab-sticky-note-body"
                value={text}
                onChange={(e) => updateText(e.target.value)}
                placeholder="Add a note…"
                disabled={readOnly}
                rows={5}
            />
        </div>
    );
}
