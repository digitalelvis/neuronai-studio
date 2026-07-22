import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
    Background,
    Controls,
    MiniMap,
    Panel,
    ReactFlow,
    ReactFlowProvider,
    addEdge,
    useEdgesState,
    useNodesState,
    useReactFlow,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';

import { CanvasUiProvider } from './CanvasUiContext';
import WorkflowEdge from './edges/WorkflowEdge';
import WorkflowNode from './nodes/WorkflowNode';
import { dispatchNodeEdit } from './inspector/nodeUtils';
import { useUndoRedo } from './hooks/useUndoRedo';
import { layoutWithDagre } from './layout';
import {
    buildFlowEdge,
    buildFlowNode,
    canSpliceNodeType,
    dropFlowPosition,
    edgeMidpoint,
    findEdgeNearPoint,
    FLOW_NODE_HEIGHT,
    FLOW_NODE_WIDTH,
    spliceNodeIntoEdge,
    toFlowEdges,
    toFlowNodes,
    toPackageGraph,
} from './graph';
import './canvas.css';

const nodeTypes = { workflowNode: WorkflowNode };
const edgeTypes = { workflowEdge: WorkflowEdge };

function WorkflowCanvasInner({
    graph,
    nodeTypesMeta,
    onGraphChange,
    readOnly = false,
    defaultProvider = '',
    defaultModel = '',
    agents = [],
}) {
    const initialNodes = useMemo(() => toFlowNodes(graph?.nodes, nodeTypesMeta), []);
    const initialEdges = useMemo(() => toFlowEdges(graph?.edges), []);
    const initialViewport = graph?.viewport || { x: 0, y: 0, zoom: 1 };

    const [nodes, setNodes, onNodesChange] = useNodesState(initialNodes);
    const [edges, setEdges, onEdgesChange] = useEdgesState(initialEdges);
    const [runStatus, setRunStatus] = useState(null);
    const isTestRunning = runStatus === 'running';
    const { getViewport, setViewport, deleteElements, screenToFlowPosition, fitView, getNodes, getEdges } =
        useReactFlow();
    const selectedNodeIdRef = useRef(null);
    const historySeededRef = useRef(false);
    const didFitViewRef = useRef(false);

    const { seedHistory, takeSnapshot, undo, redo, canUndo, canRedo } = useUndoRedo(setNodes, setEdges);

    const exportGraph = useCallback(() => {
        const viewport = getViewport();
        return toPackageGraph(nodes, edges, viewport);
    }, [nodes, edges, getViewport]);

    useEffect(() => {
        window.__workflowGraphExport = exportGraph;
        onGraphChange?.(exportGraph());
    }, [exportGraph, onGraphChange]);

    useEffect(() => {
        if (!historySeededRef.current) {
            seedHistory(initialNodes, initialEdges);
            historySeededRef.current = true;
        }
    }, [initialEdges, initialNodes, seedHistory]);

    useEffect(() => {
        takeSnapshot(nodes, edges);
    }, [nodes, edges, takeSnapshot]);

    useEffect(() => {
        if (initialViewport.x || initialViewport.y || initialViewport.zoom !== 1) {
            setViewport(initialViewport, { duration: 0 });
            didFitViewRef.current = true;
            return;
        }

        if (!didFitViewRef.current && nodes.length > 0) {
            fitView({ padding: 0.2, duration: 0 });
            didFitViewRef.current = true;
        }
    }, [initialViewport, nodes.length, setViewport, fitView]);

    const setExecutionStatus = useCallback(
        (nodeId, status) => {
            if (!nodeId) {
                return;
            }

            setNodes((current) =>
                current.map((node) =>
                    node.id === nodeId
                        ? { ...node, data: { ...node.data, executionStatus: status } }
                        : node,
                ),
            );
        },
        [setNodes],
    );

    const setLoopIteration = useCallback(
        (nodeId, iteration, maxSteps) => {
            if (!nodeId) {
                return;
            }

            setNodes((current) =>
                current.map((node) =>
                    node.id === nodeId
                        ? {
                              ...node,
                              data: {
                                  ...node.data,
                                  loopIteration: { iteration, maxSteps },
                              },
                          }
                        : node,
                ),
            );
        },
        [setNodes],
    );

    const clearExecutionStatus = useCallback(() => {
        setNodes((current) =>
            current.map((node) => ({
                ...node,
                data: { ...node.data, executionStatus: null, loopIteration: null },
            })),
        );
        setRunStatus(null);
    }, [setNodes]);

    const syncSelection = useCallback(
        (nodeId, nodeList = nodes, { silent = false } = {}) => {
            if (isTestRunning && !silent) {
                return;
            }

            selectedNodeIdRef.current = nodeId;
            const node = nodeId ? nodeList.find((n) => n.id === nodeId) : null;
            const payload = node
                ? {
                      id: node.id,
                      type: node.data.nodeType,
                      position: node.position,
                      data: node.data.config || {},
                      silent,
                  }
                : { silent };

            window.dispatchEvent(new CustomEvent('canvas-node-selected', { detail: payload }));
        },
        [isTestRunning, nodes],
    );

    const loadGraph = useCallback(
        (nextGraph) => {
            if (!nextGraph) {
                return;
            }

            const flowNodes = toFlowNodes(nextGraph.nodes, nodeTypesMeta);
            const flowEdges = toFlowEdges(nextGraph.edges);
            const viewport = nextGraph.viewport || { x: 0, y: 0, zoom: 1 };

            setNodes(flowNodes);
            setEdges(flowEdges);
            seedHistory(flowNodes, flowEdges);
            historySeededRef.current = true;
            didFitViewRef.current = false;
            syncSelection(null);

            if (viewport.x || viewport.y || viewport.zoom !== 1) {
                setViewport(viewport, { duration: 0 });
                didFitViewRef.current = true;
            } else if (flowNodes.length > 0) {
                window.requestAnimationFrame(() => {
                    fitView({ padding: 0.2, duration: 0 });
                    didFitViewRef.current = true;
                });
            }

            window.dispatchEvent(new CustomEvent('workflow-canvas-loaded', { detail: nextGraph }));
        },
        [fitView, nodeTypesMeta, seedHistory, setEdges, setNodes, setViewport, syncSelection],
    );

    useEffect(() => {
        window.__workflowCanvasLoadGraph = loadGraph;
    }, [loadGraph]);

    const onReconnect = useCallback(
        (oldEdge, newConnection) => {
            if (readOnly) {
                return;
            }

            setEdges((current) =>
                current.map((edge) =>
                    edge.id === oldEdge.id ? buildFlowEdge({ ...edge, ...newConnection }) : edge,
                ),
            );
        },
        [readOnly, setEdges],
    );

    const onConnect = useCallback(
        (connection) => {
            if (readOnly) {
                return;
            }

            setEdges((current) => addEdge(buildFlowEdge(connection), current));
        },
        [readOnly, setEdges],
    );

    const onSelectionChange = useCallback(
        ({ nodes: selectedNodes }) => {
            if (isTestRunning) {
                return;
            }

            syncSelection(selectedNodes[0]?.id ?? null);
        },
        [isTestRunning, syncSelection],
    );

    const addNodeAt = useCallback(
        (type, position) => {
            if (readOnly || !position) {
                return;
            }

            const currentNodes = getNodes();
            const currentEdges = getEdges();
            const dropCenter = {
                x: position.x + FLOW_NODE_WIDTH / 2,
                y: position.y + FLOW_NODE_HEIGHT / 2,
            };

            const nearEdge = findEdgeNearPoint(currentNodes, currentEdges, dropCenter);
            const shouldSplice = nearEdge && canSpliceNodeType(type);

            let nodePosition = position;

            if (shouldSplice) {
                const mid = edgeMidpoint(currentNodes, nearEdge);
                nodePosition = {
                    x: mid.x - FLOW_NODE_WIDTH / 2,
                    y: mid.y - FLOW_NODE_HEIGHT / 2,
                };
            }

            const defaultConfig =
                type === 'llm'
                    ? {
                          provider: defaultProvider,
                          model: defaultModel,
                          output_key: 'llm_response',
                          stream: true,
                      }
                    : type === 'agent'
                      ? { stream: true }
                      : type === 'invoke'
                        ? { output_key: 'invoke_result' }
                        : {};

            const node = buildFlowNode(type, nodePosition, nodeTypesMeta, defaultConfig);
            const nextNodes = [...currentNodes, node];

            setNodes(nextNodes);

            if (shouldSplice) {
                setEdges(spliceNodeIntoEdge(node.id, nearEdge, currentEdges));
            }

            syncSelection(node.id, nextNodes);
            dispatchNodeEdit(node);
        },
        [getEdges, getNodes, nodeTypesMeta, readOnly, setEdges, setNodes, syncSelection, defaultProvider, defaultModel],
    );

    const updateNodeData = useCallback(
        (nodeId, data) => {
            setNodes((current) => {
                const next = current.map((node) =>
                    node.id === nodeId
                        ? { ...node, data: { ...node.data, config: { ...node.data.config, ...data } } }
                        : node,
                );

                const updated = next.find((node) => node.id === nodeId);
                if (updated) {
                    window.requestAnimationFrame(() => {
                        syncSelection(nodeId, next, { silent: true });
                    });
                }

                return next;
            });
        },
        [setNodes, syncSelection],
    );

    const removeSelectedNode = useCallback(
        (nodeId = null) => {
            if (readOnly) {
                return;
            }

            const id = nodeId ?? selectedNodeIdRef.current;
            if (!id) {
                return;
            }

            const node = getNodes().find((item) => item.id === id);
            if (node && (node.data.nodeType === 'start' || node.data.nodeType === 'stop')) {
                return;
            }

            deleteElements({ nodes: [{ id }] });
            syncSelection(null);
        },
        [deleteElements, getNodes, readOnly, syncSelection],
    );

    const autoLayout = useCallback(() => {
        setNodes((current) => {
            const layouted = layoutWithDagre(current, edges);
            window.requestAnimationFrame(() => fitView({ padding: 0.2, duration: 300 }));
            return layouted;
        });
    }, [edges, fitView, setNodes]);

    const onDragOver = useCallback((event) => {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'copy';
    }, []);

    const onDrop = useCallback(
        (event) => {
            event.preventDefault();

            const type =
                event.dataTransfer.getData('application/x-neuronai-node') ||
                event.dataTransfer.getData('text/plain');

            if (!type) {
                return;
            }

            const position = dropFlowPosition(screenToFlowPosition, event.clientX, event.clientY);
            addNodeAt(type, position);
        },
        [addNodeAt, screenToFlowPosition],
    );

    useEffect(() => {
        const onNodeUpdated = (event) => {
            if (event.detail?.id) {
                updateNodeData(event.detail.id, event.detail.data || {});
            }
        };
        const onRemoveNode = (event) => removeSelectedNode(event.detail?.id);
        const onAutoLayout = () => {
            if (!readOnly) {
                autoLayout();
            }
        };
        const onLoadGraph = (event) => loadGraph(event.detail);
        const onRunStart = () => {
            clearExecutionStatus();
            setRunStatus('running');
            setNodes((current) => current.map((node) => ({ ...node, selected: false })));
            selectedNodeIdRef.current = null;
        };
        const onExecutionEvent = (event) => {
            const detail = event.detail || {};

            if (detail.event === 'step_started') {
                setExecutionStatus(detail.node_id, 'running');
                return;
            }

            if (detail.event === 'step_completed') {
                setExecutionStatus(detail.node_id, 'completed');
                return;
            }

            if (detail.event === 'loop_iteration') {
                setLoopIteration(detail.node_id, detail.iteration, detail.max_steps);
                setExecutionStatus(detail.node_id, 'running');
                return;
            }

            if (detail.event === 'trace_completed') {
                setRunStatus('completed');
                return;
            }

            if (detail.event === 'trace_failed') {
                setRunStatus('failed');
            }
        };

        window.addEventListener('canvas-node-updated', onNodeUpdated);
        window.addEventListener('canvas-remove-node', onRemoveNode);
        window.addEventListener('canvas-auto-layout', onAutoLayout);
        window.addEventListener('canvas-trace-start', onRunStart);
        window.addEventListener('canvas-run-start', onRunStart);
        window.addEventListener('canvas-execution-event', onExecutionEvent);
        window.addEventListener('workflow-canvas-load-graph', onLoadGraph);

        return () => {
            window.removeEventListener('canvas-node-updated', onNodeUpdated);
            window.removeEventListener('canvas-remove-node', onRemoveNode);
            window.removeEventListener('canvas-auto-layout', onAutoLayout);
            window.removeEventListener('canvas-trace-start', onRunStart);
            window.removeEventListener('canvas-run-start', onRunStart);
            window.removeEventListener('canvas-execution-event', onExecutionEvent);
            window.removeEventListener('workflow-canvas-load-graph', onLoadGraph);
        };
    }, [
        autoLayout,
        clearExecutionStatus,
        loadGraph,
        readOnly,
        removeSelectedNode,
        setExecutionStatus,
        setLoopIteration,
        setNodes,
        updateNodeData,
    ]);

    return (
        <CanvasUiProvider readOnly={readOnly} agents={agents}>
        <ReactFlow
            nodes={nodes}
            edges={edges}
            nodeTypes={nodeTypes}
            edgeTypes={edgeTypes}
            onNodesChange={onNodesChange}
            onEdgesChange={onEdgesChange}
            onConnect={readOnly || isTestRunning ? undefined : onConnect}
            onReconnect={readOnly || isTestRunning ? undefined : onReconnect}
            edgesReconnectable={!readOnly && !isTestRunning}
            onSelectionChange={onSelectionChange}
            onPaneClick={isTestRunning ? undefined : () => syncSelection(null)}
            onDragOver={readOnly || isTestRunning ? undefined : onDragOver}
            onDrop={readOnly || isTestRunning ? undefined : onDrop}
            nodesDraggable={!readOnly && !isTestRunning}
            nodesConnectable={!readOnly && !isTestRunning}
            elementsSelectable={!isTestRunning}
            minZoom={0.25}
            maxZoom={2}
            snapToGrid
            snapGrid={[16, 16]}
            deleteKeyCode={readOnly || isTestRunning ? null : ['Backspace', 'Delete']}
            className={`ab-react-flow${readOnly ? ' ab-react-flow--readonly' : ''}${isTestRunning ? ' ab-react-flow--test-running' : ''}`}
        >
            <Background gap={16} size={1} color="#334155" />
            <Controls className="ab-flow-controls" showInteractive={false} />
            <MiniMap
                className="ab-flow-minimap"
                nodeColor={(node) => {
                    const colors = { flow: '#6366f1', ai: '#8b5cf6', logic: '#f59e0b' };
                    return colors[node.data?.category] || '#6366f1';
                }}
                maskColor="rgba(15, 23, 42, 0.75)"
            />
            <Panel position="top-center" className="ab-flow-toolbar">
                {!readOnly && (
                    <>
                        <button type="button" className="ab-flow-toolbar-btn" onClick={undo} disabled={!canUndo || isTestRunning} title="Undo (Ctrl+Z)">
                            Undo
                        </button>
                        <button type="button" className="ab-flow-toolbar-btn" onClick={redo} disabled={!canRedo || isTestRunning} title="Redo (Ctrl+Shift+Z)">
                            Redo
                        </button>
                        <button type="button" className="ab-flow-toolbar-btn" onClick={autoLayout} disabled={isTestRunning} title="Auto layout">
                            Layout
                        </button>
                    </>
                )}
                {readOnly && <span className="ab-flow-toolbar-readonly">Read-only</span>}
                {runStatus && (
                    <span className={`ab-flow-run-status ab-flow-run-status--${runStatus}`}>
                        {runStatus === 'running' && 'Running…'}
                        {runStatus === 'completed' && 'Completed'}
                        {runStatus === 'failed' && 'Failed'}
                    </span>
                )}
            </Panel>
        </ReactFlow>
        </CanvasUiProvider>
    );
}

function WorkflowCanvas(props) {
    return (
        <ReactFlowProvider>
            <WorkflowCanvasInner {...props} />
        </ReactFlowProvider>
    );
}

export default WorkflowCanvas;
