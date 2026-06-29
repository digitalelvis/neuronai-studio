import { useCallback, useEffect, useState } from 'react';
import { normalizeNodeForEdit } from './nodeUtils';

export function useNodeEditor() {
    const [editingNode, setEditingNode] = useState(null);
    const [sheetOpen, setSheetOpen] = useState(false);

    const openNodeEditor = useCallback((node) => {
        const normalized = normalizeNodeForEdit(node);

        if (!normalized) {
            return;
        }

        setEditingNode(normalized);
        setSheetOpen(true);
    }, []);

    const syncNode = useCallback(
        (data) => {
            if (!editingNode) {
                return;
            }

            setEditingNode((current) => ({ ...current, data }));

            window.dispatchEvent(
                new CustomEvent('canvas-node-updated', {
                    detail: { id: editingNode.id, data },
                }),
            );
        },
        [editingNode],
    );

    const removeNode = useCallback(() => {
        if (!editingNode?.id) {
            return;
        }

        window.dispatchEvent(
            new CustomEvent('canvas-remove-node', {
                detail: { id: editingNode.id },
            }),
        );
        setEditingNode(null);
        setSheetOpen(false);
    }, [editingNode]);

    useEffect(() => {
        const onEdit = (event) => {
            if (event.detail?.id) {
                openNodeEditor(event.detail);
            }
        };

        window.addEventListener('canvas-node-edit', onEdit);
        return () => window.removeEventListener('canvas-node-edit', onEdit);
    }, [openNodeEditor]);

    useEffect(() => {
        const flush = () => {
            if (editingNode) {
                window.dispatchEvent(
                    new CustomEvent('canvas-node-updated', {
                        detail: { id: editingNode.id, data: { ...editingNode.data } },
                    }),
                );
            }
        };

        window.addEventListener('canvas-inspector-flush', flush);
        return () => window.removeEventListener('canvas-inspector-flush', flush);
    }, [editingNode]);

    const handleOpenChange = useCallback((open) => {
        setSheetOpen(open);

        if (!open) {
            setEditingNode(null);
        }
    }, []);

    return {
        editingNode,
        sheetOpen,
        setSheetOpen: handleOpenChange,
        openNodeEditor,
        syncNode,
        removeNode,
    };
}
