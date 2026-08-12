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
import { ensureLayoutedGraph, layoutWithDagre } from './layout';
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
    forkBranchIdsFromConfig,
    intentIdsFromConfig,
    switchCaseIdsFromConfig,
    isToolBindingEdge,
    pruneOrphanNamedHandleEdges,
    spliceNodeIntoEdge,
    syncNamedSourceHandleEdges,
    toFlowEdges,
    toFlowNodes,
    toPackageGraph,
} from './graph';
import './canvas.css';
import { isToolModeEnabled } from './inspector/nodeUtils';
import { getVariableInspectState, subscribeVariableInspect } from './chrome/variable-inspect';
import { setStateVariableGraphSnapshot } from './inspector/shared/stateVariables';

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
    onValidate,
    readOnly = false,
    defaultProvider = '',
    defaultModel = '',
    agents = [],
    workflows = [],
    tools = [],
    mcpServers = [],
    knowledgeBases = [],
    ragSearchUrlTemplate = '',
    outputClasses = [],
    providers = {},
    providerModels = {},
    variables = [],
}) {
    const initialEdges = useMemo(() => toFlowEdges(graph?.edges), []);
    const initialNodes = useMemo(() => {
        const flowNodes = toFlowNodes(graph?.nodes, nodeTypesMeta, graph?.annotations);

        return ensureLayoutedGraph(flowNodes, initialEdges);
    }, []);
    const initialViewport = useMemo(
        () => graph?.viewport || { x: 0, y: 0, zoom: 1 },
        [graph?.viewport],
    );

    const [nodes, setNodes, onNodesChange] = useNodesState(initialNodes);
    const [edges, setEdges, onEdgesChange] = useEdgesState(initialEdges);
    const [runStatus, setRunStatus] = useState(null);
    const [minimapOpen, setMinimapOpen] = useState(false);
    const isTestRunning = runStatus === 'running';
    const isTestRunningRef = useRef(false);
    isTestRunningRef.current = isTestRunning;
    const { getViewport, setViewport, deleteElements, screenToFlowPosition, fitView, getNodes, getEdges, setCenter } =
        useReactFlow();
    const selectedNodeIdRef = useRef(null);
    const historySeededRef = useRef(false);
    const didFitViewRef = useRef(false);
    /** After a node drag, RF may still emit click — suppress opening the inspector. */
    const suppressNodeClickRef = useRef(false);

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
        setStateVariableGraphSnapshot(nodes, edges);
    }, [nodes, edges]);

    // Apply saved viewport / fitView once on mount — never re-run on add/delete
    // (nodes.length used to re-apply initialViewport and yank the camera back to start).
    useEffect(() => {
        if (didFitViewRef.current) {
            return;
        }

        if (initialViewport.x || initialViewport.y || initialViewport.zoom !== 1) {
            setViewport(initialViewport, { duration: 0 });
            didFitViewRef.current = true;
            return;
        }

        if (initialNodes.length > 0) {
            fitView({ padding: 0.2, duration: 0 });
            didFitViewRef.current = true;
        }
    }, [initialViewport, initialNodes.length, setViewport, fitView]);

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

    const applyCachedExecutionStatus = useCallback(
        (nodeIds) => {
            const idSet = new Set(Array.isArray(nodeIds) ? nodeIds : []);
            if (idSet.size === 0) {
                return;
            }

            setNodes((current) =>
                current.map((node) => {
                    const nextStatus = idSet.has(node.id) ? 'completed' : null;
                    if (node.data?.executionStatus === nextStatus) {
                        return node;
                    }

                    return {
                        ...node,
                        data: { ...node.data, executionStatus: nextStatus },
                    };
                }),
            );
            setRunStatus('completed');
        },
        [setNodes],
    );

    useEffect(() => {
        const cached = getVariableInspectState();
        if (!isTestRunningRef.current && cached.completedNodeIds?.length) {
            applyCachedExecutionStatus(cached.completedNodeIds);
        }

        return subscribeVariableInspect((next) => {
            if (isTestRunningRef.current) {
                return;
            }

            if (next.completedNodeIds?.length) {
                applyCachedExecutionStatus(next.completedNodeIds);
            }
        });
    }, [applyCachedExecutionStatus]);

    const syncSelection = useCallback(
        (nodeId, nodeList = nodes, { silent = false } = {}) => {
            if (isTestRunning && !silent) {
                return;
            }

            selectedNodeIdRef.current = nodeId;
            const node = nodeId ? nodeList.find((n) => n.id === nodeId) : null;
            const nodeType = node?.data?.nodeType;

            // start/stop have no inspector config — clear selection payload instead of opening the sidebar
            if (node && (nodeType === 'start' || nodeType === 'stop')) {
                window.dispatchEvent(new CustomEvent('canvas-node-selected', { detail: { silent } }));
                return;
            }

            const payload = node
                ? {
                      id: node.id,
                      type: nodeType,
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

            const flowNodesRaw = toFlowNodes(nextGraph.nodes, nodeTypesMeta, nextGraph.annotations);
            const healed = pruneOrphanNamedHandleEdges(flowNodesRaw, toFlowEdges(nextGraph.edges));
            const flowEdges = healed.edges;
            const flowNodes = ensureLayoutedGraph(healed.nodes, flowEdges);
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

    const isValidConnection = useCallback(
        (connection) => {
            const source = getNodes().find((node) => node.id === connection.source);
            const target = getNodes().find((node) => node.id === connection.target);

            if (!source || !target) {
                return false;
            }

            if (source.data?.nodeType === 'note' || target.data?.nodeType === 'note') {
                return false;
            }

            const sourceHandle = connection.sourceHandle || 'default';
            const targetHandle = connection.targetHandle || 'default';
            const sourceToolMode = isToolModeEnabled(source.data?.config || {});
            const targetToolMode = isToolModeEnabled(target.data?.config || {});

            if (targetHandle === 'tools' || sourceHandle === 'toolset') {
                if (target.data?.nodeType !== 'agent' || targetHandle !== 'tools') {
                    return false;
                }

                if (sourceHandle === 'toolset') {
                    return (
                        (source.data?.nodeType === 'agent' || source.data?.nodeType === 'run_workflow') &&
                        sourceToolMode
                    );
                }

                return source.data?.nodeType === 'tool' || source.data?.nodeType === 'mcp';
            }

            // Control-flow edges cannot touch Tool Mode nodes.
            if (sourceToolMode || targetToolMode) {
                return false;
            }

            return true;
        },
        [getNodes],
    );

    const onReconnect = useCallback(
        (oldEdge, newConnection) => {
            if (readOnly) {
                return;
            }

            if (!isValidConnection(newConnection)) {
                return;
            }

            setEdges((current) =>
                current.map((edge) =>
                    edge.id === oldEdge.id ? buildFlowEdge({ ...edge, ...newConnection }) : edge,
                ),
            );
        },
        [isValidConnection, readOnly, setEdges],
    );

    const onConnect = useCallback(
        (connection) => {
            if (readOnly) {
                return;
            }

            if (!isValidConnection(connection)) {
                return;
            }

            setEdges((current) => addEdge(buildFlowEdge(connection), current));
        },
        [isValidConnection, readOnly, setEdges],
    );

    // Selection alone must not open the inspector — that would open mid-drag.
    // Click opens (onNodeClick); empty selection closes; drag only moves.
    const onSelectionChange = useCallback(
        ({ nodes: selectedNodes }) => {
            if (isTestRunning) {
                return;
            }

            const id = selectedNodes[0]?.id ?? null;
            if (!id) {
                if (selectedNodeIdRef.current) {
                    syncSelection(null);
                }
                return;
            }

            selectedNodeIdRef.current = id;
        },
        [isTestRunning, syncSelection],
    );

    const onNodeDragStart = useCallback(() => {
        suppressNodeClickRef.current = true;
    }, []);

    const onNodeDragStop = useCallback(() => {
        // Clear after the click event (if any) has had a chance to see the flag.
        window.setTimeout(() => {
            suppressNodeClickRef.current = false;
        }, 0);
    }, []);

    const onNodeClick = useCallback(
        (_event, node) => {
            if (isTestRunning) {
                return;
            }

            if (suppressNodeClickRef.current) {
                suppressNodeClickRef.current = false;
                return;
            }

            syncSelection(node.id);
        },
        [isTestRunning, syncSelection],
    );

    const addNodeAt = useCallback(
        (type, position, seedConfig = {}) => {
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
                          vision: true,
                      }
                    : type === 'intent_classifier'
                      ? {
                            provider: defaultProvider,
                            model: defaultModel,
                            message: '{{input}}',
                            output_key: 'intent',
                            vision: false,
                            memory: false,
                            intents: [
                                {
                                    id: 'after_sales',
                                    name: 'After sales',
                                    description: 'Question related to after sales',
                                },
                                {
                                    id: 'how_to',
                                    name: 'How to use',
                                    description: 'Questions about how to use products',
                                },
                                {
                                    id: 'other',
                                    name: 'Other',
                                    description: 'Other questions',
                                },
                            ],
                        }
                      : type === 'switch'
                        ? {
                              cases: [
                                  {
                                      id: 'case_1',
                                      label: 'Case 1',
                                      state_key: 'input',
                                      operator: 'not_empty',
                                      value: null,
                                      value_type: 'auto',
                                      strict: false,
                                  },
                              ],
                          }
                      : type === 'agent'
                      ? {
                            config_mode: 'inline',
                            tool_mode: false,
                            stream: true,
                            provider: defaultProvider,
                            model: defaultModel,
                            output_key: 'agent_response',
                        }
                      : type === 'invoke'
                        ? { output_key: 'invoke_result' }
                        : type === 'run_workflow'
                          ? {
                                workflow_id: '',
                                message: '{{input}}',
                                state_map: [],
                                output_key: 'child_output',
                                tool_mode: false,
                            }
                          : type === 'tool'
                            ? { output_key: 'tool_result' }
                            : type === 'mcp'
                              ? { output_key: 'mcp_result' }
                              : type === 'note'
                                ? { text: '' }
                                : {};

            const node = buildFlowNode(type, nodePosition, nodeTypesMeta, {
                ...defaultConfig,
                ...seedConfig,
            });
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
                const previous = current.find((node) => node.id === nodeId);
                const previousConfig = previous?.data?.config || {};
                const previousToolMode = isToolModeEnabled(previousConfig);
                const nextToolMode = isToolModeEnabled(data || {});
                const nodeType = previous?.data?.nodeType;

                if (nextToolMode && !previousToolMode) {
                    setEdges((edges) =>
                        edges.filter((edge) => {
                            if (edge.source !== nodeId && edge.target !== nodeId) {
                                return true;
                            }

                            return isToolBindingEdge(edge);
                        }),
                    );
                }

                if (!nextToolMode && previousToolMode) {
                    setEdges((edges) =>
                        edges.filter(
                            (edge) =>
                                !(edge.source === nodeId && (edge.sourceHandle || 'default') === 'toolset'),
                        ),
                    );
                }

                if (nodeType === 'intent_classifier' && Array.isArray(data?.intents)) {
                    const previousIds = intentIdsFromConfig(previousConfig);
                    const nextIds = intentIdsFromConfig(data);
                    setEdges((edges) =>
                        syncNamedSourceHandleEdges(edges, nodeId, previousIds, nextIds, {
                            allowDefault: false,
                        }),
                    );
                }

                if (nodeType === 'fork' && Array.isArray(data?.branches)) {
                    const previousIds = forkBranchIdsFromConfig(previousConfig);
                    const nextIds = forkBranchIdsFromConfig(data);
                    setEdges((edges) =>
                        syncNamedSourceHandleEdges(edges, nodeId, previousIds, nextIds, {
                            allowDefault: true,
                        }),
                    );
                }

                if (nodeType === 'switch' && Array.isArray(data?.cases)) {
                    const previousIds = switchCaseIdsFromConfig(previousConfig);
                    const nextIds = switchCaseIdsFromConfig(data);
                    setEdges((edges) =>
                        syncNamedSourceHandleEdges(edges, nodeId, previousIds, nextIds, {
                            allowDefault: true,
                        }),
                    );
                }

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
        [setEdges, setNodes, syncSelection],
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

            let seedConfig = {};
            const rawConfig = event.dataTransfer.getData('application/x-neuronai-node-config');

            if (rawConfig) {
                try {
                    const payload = JSON.parse(rawConfig);
                    if (payload?.toolRef) {
                        seedConfig = { tool_ref: payload.toolRef, output_key: 'tool_result' };
                    } else if (payload?.mcpServer) {
                        seedConfig = { mcp_server: payload.mcpServer, output_key: 'mcp_result' };
                    }
                } catch {
                    seedConfig = {};
                }
            }

            const position = dropFlowPosition(screenToFlowPosition, event.clientX, event.clientY);
            addNodeAt(type, position, seedConfig);
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

                if (detail.node_id) {
                    setExecutionStatus(detail.node_id, 'failed');
                } else {
                    setNodes((current) =>
                        current.map((node) =>
                            node.data?.executionStatus === 'running'
                                ? { ...node, data: { ...node.data, executionStatus: 'failed' } }
                                : node,
                        ),
                    );
                }
            }
        };

        const onClearSelection = () => {
            setNodes((current) => current.map((node) => ({ ...node, selected: false })));
            syncSelection(null);
        };

        const applyCompletedFromCache = (nodeIds) => {
            const idSet = new Set(Array.isArray(nodeIds) ? nodeIds : []);
            if (idSet.size === 0) {
                return;
            }

            setNodes((current) =>
                current.map((node) => {
                    const nextStatus = idSet.has(node.id) ? 'completed' : null;
                    if (node.data?.executionStatus === nextStatus) {
                        return node;
                    }

                    return {
                        ...node,
                        data: { ...node.data, executionStatus: nextStatus },
                    };
                }),
            );
            setRunStatus('completed');
        };

        const onVariableInspectUpdated = (event) => {
            if (isTestRunning) {
                return;
            }

            applyCompletedFromCache(event.detail?.completedNodeIds);
        };

        const onVariableInspectReset = () => {
            if (isTestRunning) {
                return;
            }

            clearExecutionStatus();
        };

        window.addEventListener('canvas-node-updated', onNodeUpdated);
        window.addEventListener('canvas-remove-node', onRemoveNode);
        window.addEventListener('canvas-duplicate-node', onDuplicateNode);
        window.addEventListener('canvas-auto-layout', onAutoLayout);
        window.addEventListener('canvas-focus-node', onFocusNode);
        window.addEventListener('canvas-clear-selection', onClearSelection);
        window.addEventListener('canvas-trace-start', onRunStart);
        window.addEventListener('canvas-run-start', onRunStart);
        window.addEventListener('canvas-execution-event', onExecutionEvent);
        window.addEventListener('workflow-canvas-load-graph', onLoadGraph);
        window.addEventListener('variable-inspect-updated', onVariableInspectUpdated);
        window.addEventListener('variable-inspect-reset', onVariableInspectReset);

        return () => {
            window.removeEventListener('canvas-node-updated', onNodeUpdated);
            window.removeEventListener('canvas-remove-node', onRemoveNode);
            window.removeEventListener('canvas-duplicate-node', onDuplicateNode);
            window.removeEventListener('canvas-auto-layout', onAutoLayout);
            window.removeEventListener('canvas-focus-node', onFocusNode);
            window.removeEventListener('canvas-clear-selection', onClearSelection);
            window.removeEventListener('canvas-trace-start', onRunStart);
            window.removeEventListener('canvas-run-start', onRunStart);
            window.removeEventListener('canvas-execution-event', onExecutionEvent);
            window.removeEventListener('workflow-canvas-load-graph', onLoadGraph);
            window.removeEventListener('variable-inspect-updated', onVariableInspectUpdated);
            window.removeEventListener('variable-inspect-reset', onVariableInspectReset);
        };
    }, [
        autoLayout,
        clearExecutionStatus,
        duplicateNode,
        getNodes,
        isTestRunning,
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
                if (editing) {
                    return;
                }

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
            workflows={workflows}
            tools={tools}
            mcpServers={mcpServers}
            knowledgeBases={knowledgeBases}
            ragSearchUrlTemplate={ragSearchUrlTemplate}
            outputClasses={outputClasses}
            providers={providers}
            providerModels={providerModels}
            variables={variables}
            defaultProvider={defaultProvider}
            defaultModel={defaultModel}
            nodeTypesMeta={nodeTypesMeta}
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
                    isValidConnection={isValidConnection}
                    edgesReconnectable={!readOnly && !isTestRunning}
                    onSelectionChange={onSelectionChange}
                    onNodeClick={isTestRunning ? undefined : onNodeClick}
                    onNodeDragStart={readOnly || isTestRunning ? undefined : onNodeDragStart}
                    onNodeDragStop={readOnly || isTestRunning ? undefined : onNodeDragStop}
                    onPaneClick={isTestRunning ? undefined : () => syncSelection(null)}
                    onDragOver={readOnly || isTestRunning ? undefined : onDragOver}
                    onDrop={readOnly || isTestRunning ? undefined : onDrop}
                    nodesDraggable={!readOnly && !isTestRunning}
                    nodesConnectable={!readOnly && !isTestRunning}
                    elementsSelectable={!isTestRunning}
                    selectNodesOnDrag={false}
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
                        {typeof onValidate === 'function' && (
                            <button
                                type="button"
                                className="ab-flow-toolbar-btn"
                                onClick={onValidate}
                                disabled={isTestRunning}
                                title="Validate graph"
                            >
                                Validate
                            </button>
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
