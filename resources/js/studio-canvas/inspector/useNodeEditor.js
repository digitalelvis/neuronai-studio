import { useCallback, useEffect, useState } from 'react';
import { normalizeNodeForEdit } from './nodeUtils';
import { normalizeNodeTitle } from '../graph';

const INSPECTOR_WIDTH_KEY = 'ab-inspector-width';

export function getStoredInspectorSize(fallback = 28) {
    try {
        const raw = window.localStorage.getItem(INSPECTOR_WIDTH_KEY);
        const value = raw != null ? Number(raw) : NaN;
        if (Number.isFinite(value) && value >= 18 && value <= 48) {
            return value;
        }
    } catch {
        // ignore storage errors
    }

    return fallback;
}

export function storeInspectorSize(size) {
    try {
        if (Number.isFinite(size)) {
            window.localStorage.setItem(INSPECTOR_WIDTH_KEY, String(Math.round(size)));
        }
    } catch {
        // ignore storage errors
    }
}

export function useNodeEditor() {
    const [editingNode, setEditingNode] = useState(null);
    const [section, setSection] = useState('all');

    const openNodeEditor = useCallback((node, nextSection = 'all') => {
        if (!node?.id || node.type === 'note') {
            setEditingNode(null);
            setSection('all');
            return;
        }

        const normalized = normalizeNodeForEdit(node);

        if (!normalized) {
            return;
        }

        setEditingNode({
            id: node.id,
            type: node.type,
            title: normalizeNodeTitle(node.title),
            typeLabel: node.typeLabel || node.type,
            existingTitles: node.existingTitles || [],
            data: normalized.data,
        });
        setSection(nextSection);
    }, []);

    const closeNodeEditor = useCallback(() => {
        setEditingNode(null);
        setSection('all');
        window.dispatchEvent(new CustomEvent('canvas-clear-selection'));
    }, []);

    const syncNodeTitle = useCallback(
        (title) => {
            if (!editingNode) {
                return;
            }

            const normalized = normalizeNodeTitle(title);

            setEditingNode((current) =>
                current
                    ? {
                          ...current,
                          title: normalized,
                      }
                    : current,
            );

            window.dispatchEvent(
                new CustomEvent('canvas-node-title-updated', {
                    detail: { id: editingNode.id, title: normalized, source: 'inspector' },
                }),
            );
        },
        [editingNode],
    );

    const syncNode = useCallback(
        (data) => {
            if (!editingNode) {
                return;
            }

            setEditingNode((current) => (current ? { ...current, data } : current));

            window.dispatchEvent(
                new CustomEvent('canvas-node-updated', {
                    detail: { id: editingNode.id, data, source: 'inspector' },
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
        setSection('all');
    }, [editingNode]);

    useEffect(() => {
        const onSelected = (event) => {
            const detail = event.detail || {};

            // Silent selection syncs (e.g. after canvas-node-updated) should not remount the form.
            if (detail.silent) {
                if (!detail.id) {
                    return;
                }

                setEditingNode((current) => {
                    if (!current || current.id !== detail.id) {
                        return current;
                    }

                    return {
                        ...current,
                        title: detail.title !== undefined ? normalizeNodeTitle(detail.title) : current.title,
                        typeLabel: detail.typeLabel ?? current.typeLabel,
                        existingTitles: detail.existingTitles ?? current.existingTitles,
                        data: { ...current.data, ...(detail.data || {}) },
                    };
                });
                return;
            }

            if (!detail.id) {
                setEditingNode(null);
                setSection('all');
                return;
            }

            if (detail.type === 'note' || detail.type === 'start') {
                setEditingNode(null);
                setSection('all');
                return;
            }

            openNodeEditor(
                {
                    id: detail.id,
                    type: detail.type,
                    title: detail.title,
                    typeLabel: detail.typeLabel,
                    existingTitles: detail.existingTitles,
                    data: detail.data || {},
                },
                'all',
            );
        };

        const onEdit = (event) => {
            if (!event.detail?.id) {
                return;
            }

            openNodeEditor(event.detail, event.detail.section || 'all');
        };

        window.addEventListener('canvas-node-selected', onSelected);
        window.addEventListener('canvas-node-edit', onEdit);
        return () => {
            window.removeEventListener('canvas-node-selected', onSelected);
            window.removeEventListener('canvas-node-edit', onEdit);
        };
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

    // Keep sidebar form in sync when the same node is updated from elsewhere.
    useEffect(() => {
        const onUpdated = (event) => {
            const detail = event.detail || {};
            if (!detail.id || detail.source === 'inspector') {
                return;
            }

            setEditingNode((current) => {
                if (!current || detail.id !== current.id) {
                    return current;
                }

                return {
                    ...current,
                    data: { ...current.data, ...(detail.data || {}) },
                };
            });
        };

        window.addEventListener('canvas-node-updated', onUpdated);
        return () => window.removeEventListener('canvas-node-updated', onUpdated);
    }, []);

    return {
        editingNode,
        section,
        openNodeEditor,
        closeNodeEditor,
        syncNode,
        syncNodeTitle,
        removeNode,
    };
}
