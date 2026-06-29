import { useCallback, useEffect, useRef, useState } from 'react';

function cloneState(nodes, edges) {
    return {
        nodes: structuredClone(nodes),
        edges: structuredClone(edges),
    };
}

export function useUndoRedo(setNodes, setEdges, { maxHistory = 40, debounceMs = 400 } = {}) {
    const historyRef = useRef([]);
    const indexRef = useRef(-1);
    const skipRef = useRef(false);
    const debounceRef = useRef(null);
    const [canUndo, setCanUndo] = useState(false);
    const [canRedo, setCanRedo] = useState(false);

    const syncFlags = useCallback(() => {
        setCanUndo(indexRef.current > 0);
        setCanRedo(indexRef.current < historyRef.current.length - 1);
    }, []);

    const applySnapshot = useCallback(
        (snapshot) => {
            skipRef.current = true;
            setNodes(snapshot.nodes);
            setEdges(snapshot.edges);
            skipRef.current = false;
        },
        [setNodes, setEdges],
    );

    const seedHistory = useCallback(
        (nodes, edges) => {
            historyRef.current = [cloneState(nodes, edges)];
            indexRef.current = 0;
            syncFlags();
        },
        [syncFlags],
    );

    const takeSnapshot = useCallback(
        (nodes, edges) => {
            if (skipRef.current) {
                return;
            }

            if (debounceRef.current) {
                clearTimeout(debounceRef.current);
            }

            debounceRef.current = setTimeout(() => {
                const snapshot = cloneState(nodes, edges);
                const current = historyRef.current[indexRef.current];
                const serializedCurrent = JSON.stringify(current);
                const serializedNext = JSON.stringify(snapshot);

                if (serializedCurrent === serializedNext) {
                    return;
                }

                historyRef.current = historyRef.current.slice(0, indexRef.current + 1);
                historyRef.current.push(snapshot);

                while (historyRef.current.length > maxHistory) {
                    historyRef.current.shift();
                }

                indexRef.current = historyRef.current.length - 1;
                syncFlags();
            }, debounceMs);
        },
        [debounceMs, maxHistory, syncFlags],
    );

    const undo = useCallback(() => {
        if (indexRef.current <= 0) {
            return;
        }

        indexRef.current -= 1;
        applySnapshot(historyRef.current[indexRef.current]);
        syncFlags();
    }, [applySnapshot, syncFlags]);

    const redo = useCallback(() => {
        if (indexRef.current >= historyRef.current.length - 1) {
            return;
        }

        indexRef.current += 1;
        applySnapshot(historyRef.current[indexRef.current]);
        syncFlags();
    }, [applySnapshot, syncFlags]);

    useEffect(() => {
        const onKeyDown = (event) => {
            const key = event.key.toLowerCase();
            const meta = event.metaKey || event.ctrlKey;

            if (!meta || event.altKey) {
                return;
            }

            if (key === 'z' && event.shiftKey) {
                event.preventDefault();
                redo();
                return;
            }

            if (key === 'z') {
                event.preventDefault();
                undo();
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [redo, undo]);

    useEffect(
        () => () => {
            if (debounceRef.current) {
                clearTimeout(debounceRef.current);
            }
        },
        [],
    );

    return {
        seedHistory,
        takeSnapshot,
        undo,
        redo,
        canUndo,
        canRedo,
    };
}
