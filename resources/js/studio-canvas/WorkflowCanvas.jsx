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
    useStore,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';

import { CanvasUiProvider } from './CanvasUiContext';
import WorkflowEdge from './edges/WorkflowEdge';
import WorkflowNode from './nodes/WorkflowNode';
import StickyNote from './nodes/StickyNote';
import { useUndoRedo } from './hooks/useUndoRedo';
import { layoutWithDagre } from './layout';
import {
    buildFlowEdge,
    buildFlowNode,
    canSpliceNodeType,
    createNodeId,
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

const nodeTypes = { workflowNode: WorkflowNode, stickyNote: StickyNote };
const edgeTypes = { workflowEdge: WorkflowEdge };

function ZoomPercent() {
    const zoom = useStore((state) => state.transform[2] ?? 1);
    return <span className="ab-zoom-percent">{Math.round(zoom * 100)}%</span>;
}

function CanvasEmptyState({ visible }) {
    if (!visible) {
        return null;
    }

    return (
        <div className="ab-canvas-empty pointer-events-none absolute inset-0 z-10 flex items-center justify-center">
            <div className="rounded-xl border border-border/70 bg-card/80 px-6 py-5 text-center shadow-lg backdrop-blur-sm">
                <p className="text-sm font-medium text-foreground">Build your workflow</p>
                <p className="mt-1 max-w-xs text-xs text-muted-foreground">
                    Drag components from the left palette onto the canvas. Search to find agents, tools, and logic nodes.
                </p>
            </div>
        </div>
    );
}

function WorkflowCanvasInner({
    graph,
    nodeTypesMeta,
    onGraphChange,
    readOnly = false,
    defaultProvider = '',
    defaultModel = '',
    agents = [],
    tools = [],
    mcpServers = [],
    knowledgeBases = [],
    ragSearchUrlTemplate = '',
    outputClasses = [],
    providers = {},
    providerModels = {},
}) {
    const initialNodes = useMemo(
        () => toFlowNodes(graph?.nodes, nodeTypesMeta, graph?.annotations),
        [],
    );
    const initialEdges = useMemo(() => toFlowEdges(graph?.edges), []);
    const initialViewport = graph?.viewport || { x: 0, y: 0, zoom: 1 };

    const [nodes, setNodes, onNodesChange] = useNodesState(initialNodes);
    const [edges, setEdges, onEdgesChange] = useEdgesState(initialEdges);
    const [runStatus, setRunStatus] = useState(null);
    const [minimapOpen, setMinimapOpen] = useState(true);
    const isTestRunning = runStatus === 'running';
    const { getViewport, setViewport, deleteElements, screenToFlowPosition, fitView, getNodes, getEdges, setCenter } =
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

    const showEmptyState = useMemo(() => {
        const workflowNodes = nodes.filter((node) => node.data?.nodeType !== 'note');
        return workflowNodes.every((node) => ['start', 'stop'].includes(node.data?.nodeType));
    }, [nodes]);

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

            const flowNodes = toFlowNodes(nextGraph.nodes, nodeTypesMeta, nextGraph.annotations);
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

            const source = getNodes().find((node) => node.id === connection.source);
            const target = getNodes().find((node) => node.id === connection.target);
            if (source?.data?.nodeType === 'note' || target?.data?.nodeType === 'note') {
                return;
            }

            setEdges((current) => addEdge(buildFlowEdge(connection), current));
        },
        [getNodes, readOnly, setEdges],
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

            const nearEdge = type === 'note' ? null : findEdgeNearPoint(currentNodes, currentEdges, dropCenter);
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
                        : type === 'note'
                          ? { text: '' }
                          : {};

            const node = buildFlowNode(type, nodePosition, nodeTypesMeta, defaultConfig);
            const nextNodes = [...currentNodes, node];

            setNodes(nextNodes);

            if (shouldSplice) {
                setEdges(spliceNodeIntoEdge(node.id, nearEdge, currentEdges));
            }

            syncSelection(node.id, nextNodes);
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

    const duplicateNode = useCallback(
        (nodeId) => {
            if (readOnly || !nodeId) {
                return;
            }

            const current = getNodes();
            const source = current.find((node) => node.id === nodeId);
            if (!source || source.data.nodeType === 'start' || source.data.nodeType === 'stop') {
                return;
            }

            const clone = {
                ...source,
                id: createNodeId(source.data.nodeType),
                position: {
                    x: source.position.x + 40,
                    y: source.position.y + 40,
                },
                selected: true,
                data: {
                    ...source.data,
                    config: { ...(source.data.config || {}) },
                    executionStatus: null,
                },
            };

            const nextNodes = current.map((node) => ({ ...node, selected: false })).concat(clone);
            setNodes(nextNodes);
            syncSelection(clone.id, nextNodes);
        },
        [getNodes, readOnly, setNodes, syncSelection],
    );

    const autoLayout = useCallback(() => {
        setNodes((current) => {
            const workflowOnly = current.filter((node) => node.data?.nodeType !== 'note');
            const notes = current.filter((node) => node.data?.nodeType === 'note');
            const layouted = layoutWithDagre(workflowOnly, edges);
            window.requestAnimationFrame(() => fitView({ padding: 0.2, duration: 300 }));
            return [...layouted, ...notes];
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
        const onDuplicateNode = (event) => duplicateNode(event.detail?.id);
        const onAutoLayout = () => {
            if (!readOnly) {
                autoLayout();
            }
        };
        const onLoadGraph = (event) => loadGraph(event.detail);
        const onFocusNode = (event) => {
            const nodeId = event.detail?.id;
            if (!nodeId) {
                return;
            }

            const node = getNodes().find((item) => item.id === nodeId);
            if (!node) {
                return;
            }

            setNodes((current) =>
                current.map((item) => ({ ...item, selected: item.id === nodeId })),
            );
            syncSelection(nodeId);
            setCenter(node.position.x + FLOW_NODE_WIDTH / 2, node.position.y + FLOW_NODE_HEIGHT / 2, {
                zoom: 1,
                duration: 300,
            });
        };
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
        window.addEventListener('canvas-duplicate-node', onDuplicateNode);
        window.addEventListener('canvas-auto-layout', onAutoLayout);
        window.addEventListener('canvas-focus-node', onFocusNode);
        window.addEventListener('canvas-trace-start', onRunStart);
        window.addEventListener('canvas-run-start', onRunStart);
        window.addEventListener('canvas-execution-event', onExecutionEvent);
        window.addEventListener('workflow-canvas-load-graph', onLoadGraph);

        return () => {
            window.removeEventListener('canvas-node-updated', onNodeUpdated);
            window.removeEventListener('canvas-remove-node', onRemoveNode);
            window.removeEventListener('canvas-duplicate-node', onDuplicateNode);
            window.removeEventListener('canvas-auto-layout', onAutoLayout);
            window.removeEventListener('canvas-focus-node', onFocusNode);
            window.removeEventListener('canvas-trace-start', onRunStart);
            window.removeEventListener('canvas-run-start', onRunStart);
            window.removeEventListener('canvas-execution-event', onExecutionEvent);
            window.removeEventListener('workflow-canvas-load-graph', onLoadGraph);
        };
    }, [
        autoLayout,
        clearExecutionStatus,
        duplicateNode,
        getNodes,
        loadGraph,
        readOnly,
        removeSelectedNode,
        setCenter,
        setExecutionStatus,
        setLoopIteration,
        setNodes,
        syncSelection,
        updateNodeData,
    ]);

    useEffect(() => {
        const onKeyDown = (event) => {
            if (readOnly || isTestRunning) {
                return;
            }

            const target = event.target;
            const tag = target?.tagName?.toLowerCase();
            const editing =
                tag === 'input' ||
                tag === 'textarea' ||
                tag === 'select' ||
                target?.isContentEditable;

            if (event.key === 'Escape') {
                setNodes((current) => current.map((node) => ({ ...node, selected: false })));
                syncSelection(null);
                return;
            }

            if (editing) {
                return;
            }

            const meta = event.metaKey || event.ctrlKey;

            if (meta && event.key.toLowerCase() === 'd') {
                event.preventDefault();
                duplicateNode(selectedNodeIdRef.current);
            }
        };

        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, [duplicateNode, isTestRunning, readOnly, setNodes, syncSelection]);

    return (
        <CanvasUiProvider
            readOnly={readOnly}
            agents={agents}
            tools={tools}
            mcpServers={mcpServers}
            knowledgeBases={knowledgeBases}
            ragSearchUrlTemplate={ragSearchUrlTemplate}
            outputClasses={outputClasses}
            providers={providers}
            providerModels={providerModels}
            defaultProvider={defaultProvider}
            defaultModel={defaultModel}
        >
            <div className="relative h-full w-full">
                <CanvasEmptyState visible={showEmptyState && !isTestRunning} />
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
                    <Background gap={20} size={1} color="rgba(148, 163, 184, 0.22)" variant="dots" />
                    <Controls
                        className="ab-flow-controls"
                        showInteractive
                        position="bottom-right"
                    />
                    <Panel position="bottom-right" className="ab-zoom-cluster">
                        <ZoomPercent />
                        <button
                            type="button"
                            className="ab-flow-toolbar-btn"
                            onClick={() => setMinimapOpen((value) => !value)}
                            title="Toggle minimap"
                        >
                            {minimapOpen ? 'Hide map' : 'Show map'}
                        </button>
                    </Panel>
                    {minimapOpen && (
                        <MiniMap
                            className="ab-flow-minimap"
                            position="bottom-right"
                            nodeColor={(node) => {
                                const colors = {
                                    flow: '#6366f1',
                                    ai: '#8b5cf6',
                                    logic: '#f59e0b',
                                    utilities: '#eab308',
                                };
                                return colors[node.data?.category] || '#6366f1';
                            }}
                            maskColor="rgba(15, 23, 42, 0.75)"
                        />
                    )}
                    <Panel position="top-center" className="ab-flow-toolbar">
                        {!readOnly && (
                            <>
                                <button
                                    type="button"
                                    className="ab-flow-toolbar-btn"
                                    onClick={undo}
                                    disabled={!canUndo || isTestRunning}
                                    title="Undo (Ctrl+Z)"
                                >
                                    Undo
                                </button>
                                <button
                                    type="button"
                                    className="ab-flow-toolbar-btn"
                                    onClick={redo}
                                    disabled={!canRedo || isTestRunning}
                                    title="Redo (Ctrl+Shift+Z)"
                                >
                                    Redo
                                </button>
                                <button
                                    type="button"
                                    className="ab-flow-toolbar-btn"
                                    onClick={autoLayout}
                                    disabled={isTestRunning}
                                    title="Auto layout"
                                >
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
            </div>
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
