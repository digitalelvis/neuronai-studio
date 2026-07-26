import { useEffect, useState } from 'react';
import { Save, Upload } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ResizableHandle, ResizablePanel, ResizablePanelGroup } from '@/components/ui/resizable';
import { TooltipProvider } from '@/components/ui/tooltip';
import WorkflowCanvas from './WorkflowCanvas';
import NodePalette from './NodePalette';
import NodeEditSheet from './inspector/NodeEditSheet';
import ImportJsonDialog from './ImportJsonDialog';
import ToolExposureModal from './ToolExposureModal';
import ToolActionsModal from './ToolActionsModal';
import PlaygroundOverlay from './chrome/PlaygroundOverlay';
import ShareMenu from './chrome/ShareMenu';
import LogsDrawer from './chrome/LogsDrawer';

export default function WorkflowEditorShell({ config }) {
    const [name, setName] = useState(config.workflowName ?? '');
    const [description, setDescription] = useState(config.workflowDescription ?? '');
    const [status, setStatus] = useState(config.workflowStatus ?? 'draft');
    const [validationMessage, setValidationMessage] = useState('');
    const [importOpen, setImportOpen] = useState(false);
    const [toolExposureEdit, setToolExposureEdit] = useState(null);
    const [toolActionsEdit, setToolActionsEdit] = useState(null);
    const [toolsCatalog, setToolsCatalog] = useState(config.tools || []);
    const readOnly = config.readOnly ?? false;
    const nodeTypesMeta = config.nodeTypes || {};

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
        const syncMeta = () => {
            if (window.__NEURONAI_CANVAS_CONFIG) {
                window.__NEURONAI_CANVAS_CONFIG.workflowName = name;
                window.__NEURONAI_CANVAS_CONFIG.workflowDescription = description;
                window.__NEURONAI_CANVAS_CONFIG.workflowStatus = status;
            }
            window.dispatchEvent(new CustomEvent('workflow-meta-changed'));
        };
        syncMeta();
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

                <div className="flex shrink-0 flex-wrap items-center gap-3 border-b border-border px-4 py-2.5">
                    <Input
                        className="h-8 w-48"
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        placeholder="Workflow name"
                        disabled={readOnly}
                    />
                    <Select value={status} onValueChange={setStatus} disabled={readOnly}>
                        <SelectTrigger className="h-8 w-32">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="draft">Draft</SelectItem>
                            <SelectItem value="published">Published</SelectItem>
                        </SelectContent>
                    </Select>
                    <Textarea
                        className="min-h-8 max-h-8 w-64 resize-none py-1.5 text-xs"
                        rows={1}
                        value={description}
                        onChange={(e) => setDescription(e.target.value)}
                        placeholder="Description"
                        disabled={readOnly}
                    />

                    <div className="ml-auto flex flex-wrap items-center gap-2">
                        <Button variant="outline" size="sm" onClick={handleValidate}>
                            Validate
                        </Button>
                        {!readOnly && (
                            <>
                                <Button variant="outline" size="sm" onClick={() => setImportOpen(true)}>
                                    <Upload className="h-3.5 w-3.5" />
                                    Import
                                </Button>
                                <Button size="sm" onClick={handleSave}>
                                    <Save className="h-3.5 w-3.5" />
                                    Save
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                <ResizablePanelGroup direction="horizontal" className="min-h-0 flex-1">
                    <ResizablePanel defaultSize={20} minSize={14} maxSize={28}>
                        <NodePalette
                            nodeTypes={config.nodeTypes || {}}
                            tools={toolsCatalog}
                            mcpServers={config.mcpServers || []}
                            readOnly={readOnly}
                        />
                    </ResizablePanel>
                    <ResizableHandle withHandle />
                    <ResizablePanel defaultSize={80} minSize={50}>
                        <div className="relative h-full min-h-0 overflow-hidden">
                            <WorkflowCanvas
                                graph={config.graph}
                                nodeTypesMeta={config.nodeTypes || {}}
                                readOnly={readOnly}
                                defaultProvider={config.defaultProvider ?? ''}
                                defaultModel={config.defaultModel ?? ''}
                                agents={config.agents || []}
                                tools={toolsCatalog}
                                mcpServers={config.mcpServers || []}
                                knowledgeBases={config.knowledgeBases || []}
                                ragSearchUrlTemplate={config.ragSearchUrlTemplate ?? ''}
                                outputClasses={config.outputClasses || []}
                                providers={config.providers || {}}
                                providerModels={config.providerModels || {}}
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
                                </div>
                            </div>

                            <div className="ab-canvas-fabs-bottom pointer-events-none absolute bottom-4 left-4 z-20">
                                <div className="pointer-events-auto">
                                    <LogsDrawer
                                        workflowConfig={workflowPanelConfig}
                                        validationMessage={validationMessage}
                                    />
                                </div>
                            </div>
                        </div>
                    </ResizablePanel>
                </ResizablePanelGroup>

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
                    typeMeta={nodeTypesMeta.agent || {}}
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

                <NodeEditSheet
                    agents={config.agents || []}
                    tools={toolsCatalog}
                    mcpServers={config.mcpServers || []}
                    knowledgeBases={config.knowledgeBases || []}
                    ragSearchUrlTemplate={config.ragSearchUrlTemplate ?? ''}
                    outputClasses={config.outputClasses || []}
                    providers={config.providers || {}}
                    providerModels={config.providerModels || {}}
                    defaultProvider={config.defaultProvider ?? ''}
                    defaultModel={config.defaultModel ?? ''}
                    nodeTypesMeta={nodeTypesMeta}
                    readOnly={readOnly}
                />
            </div>
        </TooltipProvider>
    );
}
