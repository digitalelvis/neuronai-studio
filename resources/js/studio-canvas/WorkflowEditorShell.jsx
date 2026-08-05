import { useEffect, useMemo, useState } from 'react';
import { Save, Upload } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { ResizableHandle, ResizablePanel, ResizablePanelGroup } from '@/components/ui/resizable';
import { TooltipProvider } from '@/components/ui/tooltip';
import WorkflowCanvas from './WorkflowCanvas';
import NodePalette from './NodePalette';
import NodeInspectorSidebar from './inspector/NodeInspectorSidebar';
import { getStoredInspectorSize, storeInspectorSize, useNodeEditor } from './inspector/useNodeEditor';
import ImportJsonDialog from './ImportJsonDialog';
import WorkflowMetaDialog from './WorkflowMetaDialog';
import ToolExposureModal from './ToolExposureModal';
import ToolActionsModal from './ToolActionsModal';
import PlaygroundOverlay from './chrome/PlaygroundOverlay';
import ShareMenu from './chrome/ShareMenu';
import BottomDock from './chrome/BottomDock';

function syncBreadcrumbName(name) {
    const label = document.querySelector('#workflow-breadcrumb-name .studio-breadcrumb-edit-label');
    if (label) {
        label.textContent = name || 'Untitled';
    }
}

export default function WorkflowEditorShell({ config }) {
    const [name, setName] = useState(config.workflowName ?? '');
    const [description, setDescription] = useState(config.workflowDescription ?? '');
    const [status, setStatus] = useState(config.workflowStatus ?? 'draft');
    const [metaOpen, setMetaOpen] = useState(false);
    const [validationMessage, setValidationMessage] = useState('');
    const [importOpen, setImportOpen] = useState(false);
    const [toolExposureEdit, setToolExposureEdit] = useState(null);
    const [toolActionsEdit, setToolActionsEdit] = useState(null);
    const [toolsCatalog, setToolsCatalog] = useState(config.tools || []);
    const readOnly = config.readOnly ?? false;
    const nodeTypesMeta = config.nodeTypes || {};
    const inspectorDefaultSize = useMemo(() => getStoredInspectorSize(28), []);
    const { editingNode, section, syncNode, removeNode, closeNodeEditor } = useNodeEditor();
    const showInspector = Boolean(editingNode);

    useEffect(() => {
        setToolsCatalog(config.tools || []);
    }, [config.tools]);

    const workflowPanelConfig = {
        readOnly,
        workflowId: config.workflowId,
        streamUrl: config.streamUrl,
        resumeUrlTemplate: config.resumeUrlTemplate,
        uploadUrl: config.uploadUrl,
        threadsIndexUrl: config.threadsIndexUrl,
        tracesIndexUrl: config.tracesIndexUrl,
        traceShowUrlTemplate: config.traceShowUrlTemplate,
        traceShowJsonUrlTemplate: config.traceShowJsonUrlTemplate,
        enabledProtocols: config.enabledProtocols,
        integrateStreamUrls: config.integrateStreamUrls,
        integrateResumeUrls: config.integrateResumeUrls,
    };

    useEffect(() => {
        const onToolExposureEdit = (event) => {
            if (!event.detail?.id) {
                return;
            }

            setToolExposureEdit({
                id: event.detail.id,
                data: event.detail.data || {},
                nodeType: event.detail.nodeType || 'agent',
            });
        };

        const onToolActionsEdit = (event) => {
            if (!event.detail?.id) {
                return;
            }

            setToolActionsEdit({
                id: event.detail.id,
                data: event.detail.data || {},
                toolRef: event.detail.toolRef || event.detail.data?.tool_ref || '',
            });
        };

        window.addEventListener('canvas-tool-exposure-edit', onToolExposureEdit);
        window.addEventListener('canvas-tool-actions-edit', onToolActionsEdit);
        return () => {
            window.removeEventListener('canvas-tool-exposure-edit', onToolExposureEdit);
            window.removeEventListener('canvas-tool-actions-edit', onToolActionsEdit);
        };
    }, []);

    useEffect(() => {
        const onMetaEditOpen = () => {
            if (readOnly) {
                return;
            }
            setMetaOpen(true);
        };

        window.addEventListener('workflow-meta-edit-open', onMetaEditOpen);
        return () => window.removeEventListener('workflow-meta-edit-open', onMetaEditOpen);
    }, [readOnly]);

    useEffect(() => {
        if (window.__NEURONAI_CANVAS_CONFIG) {
            window.__NEURONAI_CANVAS_CONFIG.workflowName = name;
            window.__NEURONAI_CANVAS_CONFIG.workflowDescription = description;
            window.__NEURONAI_CANVAS_CONFIG.workflowStatus = status;
        }
        syncBreadcrumbName(name);
        window.dispatchEvent(new CustomEvent('workflow-meta-changed'));
    }, [name, description, status]);

    const handleValidate = async () => {
        const component = window.Livewire?.find(config.wireId);
        if (component) {
            await component.call('validateGraph');
            setValidationMessage(component.get('validationMessage') ?? '');
        }
    };
    const handleSave = () => window.dispatchEvent(new CustomEvent('workflow-canvas-save'));

    return (
        <TooltipProvider>
            <div className="flex h-full min-h-0 flex-col bg-background">
                {config.readOnlyBanner && (
                    <div className="border-b border-border bg-primary/10 px-4 py-2 text-sm text-muted-foreground">
                        {config.readOnlyBanner}
                    </div>
                )}

                <ResizablePanelGroup
                    direction="horizontal"
                    className="min-h-0 flex-1"
                    autoSaveId="ab-workflow-editor-panels"
                >
                    <ResizablePanel defaultSize={18} minSize={14} maxSize={28}>
                        <NodePalette
                            nodeTypes={config.nodeTypes || {}}
                            tools={toolsCatalog}
                            mcpServers={config.mcpServers || []}
                            readOnly={readOnly}
                        />
                    </ResizablePanel>
                    <ResizableHandle withHandle />
                    <ResizablePanel defaultSize={showInspector ? 54 : 82} minSize={40}>
                        <div className="flex h-full min-h-0 flex-col overflow-hidden">
                            <div className="relative min-h-0 flex-1 overflow-hidden">
                                <WorkflowCanvas
                                    graph={config.graph}
                                    nodeTypesMeta={config.nodeTypes || {}}
                                    readOnly={readOnly}
                                    defaultProvider={config.defaultProvider ?? ''}
                                    defaultModel={config.defaultModel ?? ''}
                                    agents={config.agents || []}
                                    workflows={config.workflows || []}
                                    tools={toolsCatalog}
                                    mcpServers={config.mcpServers || []}
                                    knowledgeBases={config.knowledgeBases || []}
                                    ragSearchUrlTemplate={config.ragSearchUrlTemplate ?? ''}
                                    outputClasses={config.outputClasses || []}
                                    providers={config.providers || {}}
                                    providerModels={config.providerModels || {}}
                                    variables={config.variables || []}
                                    onValidate={handleValidate}
                                    onGraphChange={(graph) => {
                                        window.__workflowGraph = graph;
                                        const saved = window.__NEURONAI_CANVAS_CONFIG?.savedGraph;
                                        window.__workflowGraphDirty = saved
                                            ? JSON.stringify(saved) !== JSON.stringify(graph)
                                            : false;
                                        window.dispatchEvent(new CustomEvent('workflow-graph-changed'));
                                    }}
                                />

                                <div className="ab-canvas-fabs-top pointer-events-none absolute right-4 top-4 z-20 flex items-center gap-2">
                                    <div className="pointer-events-auto flex items-center gap-2">
                                        <PlaygroundOverlay
                                            workflowConfig={workflowPanelConfig}
                                            onBeforeRun={window.saveGraphBeforeRun}
                                        />
                                        <ShareMenu workflowConfig={workflowPanelConfig} />
                                        {!readOnly && (
                                            <>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="ab-fab gap-1.5 shadow-lg"
                                                    onClick={() => setImportOpen(true)}
                                                >
                                                    <Upload className="h-3.5 w-3.5" />
                                                    Import
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    className="ab-fab gap-1.5 shadow-lg"
                                                    onClick={handleSave}
                                                >
                                                    <Save className="h-3.5 w-3.5" />
                                                    Save
                                                </Button>
                                            </>
                                        )}
                                    </div>
                                </div>
                            </div>

                            <BottomDock
                                workflowConfig={workflowPanelConfig}
                                nodeTypesMeta={nodeTypesMeta}
                                validationMessage={validationMessage}
                            />
                        </div>
                    </ResizablePanel>

                    {showInspector && (
                        <>
                            <ResizableHandle withHandle />
                            <ResizablePanel
                                defaultSize={inspectorDefaultSize}
                                minSize={20}
                                maxSize={45}
                                onResize={(size) => storeInspectorSize(size)}
                            >
                                <NodeInspectorSidebar
                                    editingNode={editingNode}
                                    section={section}
                                    onClose={closeNodeEditor}
                                    onUpdate={syncNode}
                                    onRemove={removeNode}
                                    agents={config.agents || []}
                                    workflows={config.workflows || []}
                                    tools={toolsCatalog}
                                    mcpServers={config.mcpServers || []}
                                    knowledgeBases={config.knowledgeBases || []}
                                    ragSearchUrlTemplate={config.ragSearchUrlTemplate ?? ''}
                                    outputClasses={config.outputClasses || []}
                                    providers={config.providers || {}}
                                    providerModels={config.providerModels || {}}
                                    variables={config.variables || []}
                                    defaultProvider={config.defaultProvider ?? ''}
                                    defaultModel={config.defaultModel ?? ''}
                                    nodeTypesMeta={nodeTypesMeta}
                                    readOnly={readOnly}
                                />
                            </ResizablePanel>
                        </>
                    )}
                </ResizablePanelGroup>

                <WorkflowMetaDialog
                    open={metaOpen}
                    onOpenChange={setMetaOpen}
                    name={name}
                    description={description}
                    status={status}
                    readOnly={readOnly}
                    onSave={({ name: nextName, description: nextDescription, status: nextStatus }) => {
                        setName(nextName);
                        setDescription(nextDescription);
                        setStatus(nextStatus);
                    }}
                />

                <ImportJsonDialog open={importOpen} onOpenChange={setImportOpen} />

                <ToolExposureModal
                    open={Boolean(toolExposureEdit)}
                    onOpenChange={(open) => {
                        if (!open) {
                            setToolExposureEdit(null);
                        }
                    }}
                    nodeId={toolExposureEdit?.id}
                    nodeData={toolExposureEdit?.data || {}}
                    typeMeta={nodeTypesMeta[toolExposureEdit?.nodeType] || nodeTypesMeta.agent || {}}
                    readOnly={readOnly}
                    onSave={(nextData) => {
                        if (!toolExposureEdit?.id) {
                            return;
                        }

                        window.dispatchEvent(
                            new CustomEvent('canvas-node-updated', {
                                detail: { id: toolExposureEdit.id, data: nextData },
                            }),
                        );
                        setToolExposureEdit(null);
                    }}
                />

                <ToolActionsModal
                    open={Boolean(toolActionsEdit)}
                    onOpenChange={(open) => {
                        if (!open) {
                            setToolActionsEdit(null);
                        }
                    }}
                    nodeId={toolActionsEdit?.id}
                    toolRef={toolActionsEdit?.toolRef || ''}
                    toolMeta={
                        toolsCatalog.find((tool) => tool.ref === toolActionsEdit?.toolRef) || null
                    }
                    readOnly={readOnly}
                    wireId={config.wireId}
                    onSaved={(updatedTool) => {
                        if (!updatedTool?.ref) {
                            return;
                        }

                        setToolsCatalog((current) =>
                            current.map((tool) => (tool.ref === updatedTool.ref ? updatedTool : tool)),
                        );

                        if (window.__NEURONAI_CANVAS_CONFIG) {
                            window.__NEURONAI_CANVAS_CONFIG.tools = (
                                window.__NEURONAI_CANVAS_CONFIG.tools || []
                            ).map((tool) => (tool.ref === updatedTool.ref ? updatedTool : tool));
                        }
                    }}
                />
            </div>
        </TooltipProvider>
    );
}
