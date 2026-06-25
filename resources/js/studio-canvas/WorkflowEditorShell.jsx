import { useEffect, useState } from 'react';
import { Download, Save, Upload } from 'lucide-react';
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
import { Badge } from '@/components/ui/badge';
import WorkflowCanvas from './WorkflowCanvas';
import InspectorPanel from './inspector/InspectorPanel';
import ImportJsonDialog from './ImportJsonDialog';
import { downloadWorkflowJson } from './graphJson';

export default function WorkflowEditorShell({ config }) {
    const [name, setName] = useState(config.workflowName ?? '');
    const [description, setDescription] = useState(config.workflowDescription ?? '');
    const [status, setStatus] = useState(config.workflowStatus ?? 'draft');
    const [validationMessage, setValidationMessage] = useState('');
    const [importOpen, setImportOpen] = useState(false);
    const readOnly = config.readOnly ?? false;

    useEffect(() => {
        const syncMeta = () => {
            if (window.__NEURONAI_CANVAS_CONFIG) {
                window.__NEURONAI_CANVAS_CONFIG.workflowName = name;
                window.__NEURONAI_CANVAS_CONFIG.workflowDescription = description;
                window.__NEURONAI_CANVAS_CONFIG.workflowStatus = status;
            }
        };
        syncMeta();
    }, [name, description, status]);

    const callLivewire = (method, ...args) => {
        const component = window.Livewire?.find(config.wireId);
        return component?.call(method, ...args);
    };

    const handleValidate = async () => {
        const component = window.Livewire?.find(config.wireId);
        if (component) {
            await component.call('validateGraph');
            setValidationMessage(component.get('validationMessage') ?? '');
        }
    };
    const handleExportPhp = () => callLivewire('exportWorkflow');
    const handleSave = () => window.dispatchEvent(new CustomEvent('workflow-canvas-save'));
    const handleOpenTest = () => window.dispatchEvent(new CustomEvent('workflow-open-test'));

    const paletteTypes = Object.entries(config.nodeTypes || {}).filter(
        ([type]) => !['start', 'stop'].includes(type),
    );

    return (
        <TooltipProvider>
            <div className="flex h-full min-h-0 flex-col bg-background">
                {config.readOnlyBanner && (
                    <div className="border-b border-border bg-primary/10 px-4 py-2 text-sm text-muted-foreground">
                        {config.readOnlyBanner}
                    </div>
                )}

                <div className="flex flex-wrap items-center gap-3 border-b border-border px-4 py-3">
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
                        <Button variant="outline" size="sm" onClick={handleOpenTest} disabled={!config.workflowId}>
                            Test
                        </Button>
                        <Button variant="outline" size="sm" onClick={() => downloadWorkflowJson(false)}>
                            <Download className="h-3.5 w-3.5" />
                            JSON
                        </Button>
                        {!readOnly && (
                            <>
                                <Button variant="outline" size="sm" onClick={() => setImportOpen(true)}>
                                    <Upload className="h-3.5 w-3.5" />
                                    Import
                                </Button>
                                <Button variant="outline" size="sm" onClick={handleExportPhp}>
                                    Export PHP
                                </Button>
                                <Button size="sm" onClick={handleSave}>
                                    <Save className="h-3.5 w-3.5" />
                                    Save
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                {validationMessage && (
                    <div className="border-b border-border px-4 py-1.5 text-xs text-muted-foreground">{validationMessage}</div>
                )}

                <ResizablePanelGroup direction="horizontal" className="min-h-0 flex-1">
                    <ResizablePanel id="palette" defaultSize={18} minSize={14} maxSize={28}>
                        <aside className={`flex h-full min-h-0 flex-col overflow-hidden p-3 ${readOnly ? 'opacity-60' : ''}`}>
                            <h3 className="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">Nodes</h3>
                            <p className="mb-3 text-[11px] text-muted-foreground">
                                {readOnly ? 'Read-only preview' : 'Drag to canvas'}
                            </p>
                            <div className="space-y-1.5 overflow-auto">
                                {paletteTypes.map(([type, meta]) => (
                                    <div
                                        key={type}
                                        className="cursor-grab rounded-md border border-border bg-muted/30 px-3 py-2 text-sm transition-colors hover:bg-muted/60 active:cursor-grabbing"
                                        draggable={!readOnly}
                                        data-canvas-node-type={type}
                                        role="button"
                                        tabIndex={0}
                                    >
                                        {meta.label ?? type}
                                    </div>
                                ))}
                            </div>
                        </aside>
                    </ResizablePanel>
                    <ResizableHandle withHandle />
                    <ResizablePanel id="canvas" defaultSize={57} minSize={35}>
                        <div className="h-full min-h-0 overflow-hidden">
                            <WorkflowCanvas
                                graph={config.graph}
                                nodeTypesMeta={config.nodeTypes || {}}
                                readOnly={readOnly}
                                onGraphChange={(graph) => {
                                    window.__workflowGraph = graph;
                                    const saved = window.__NEURONAI_CANVAS_CONFIG?.savedGraph;
                                    window.__workflowGraphDirty = saved ? JSON.stringify(saved) !== JSON.stringify(graph) : false;
                                    window.dispatchEvent(new CustomEvent('workflow-graph-changed'));
                                }}
                            />
                        </div>
                    </ResizablePanel>
                    <ResizableHandle withHandle />
                    <ResizablePanel id="inspector" defaultSize={25} minSize={20} maxSize={42}>
                        <InspectorPanel
                            agents={config.agents || []}
                            tools={config.tools || []}
                            mcpServers={config.mcpServers || []}
                            readOnly={readOnly}
                            workflowConfig={{
                                workflowId: config.workflowId,
                                streamUrl: config.streamUrl,
                                resumeUrlTemplate: config.resumeUrlTemplate,
                                uploadUrl: config.uploadUrl,
                            }}
                            onBeforeRun={window.saveGraphBeforeRun}
                        />
                    </ResizablePanel>
                </ResizablePanelGroup>

                <div className="flex items-center justify-between border-t border-border px-4 py-1.5 text-[11px] text-muted-foreground">
                    <span className="flex items-center gap-2">
                        <span className="inline-block h-2 w-2 rounded-full bg-green-500" />
                        Online
                    </span>
                    {config.workflowId && (
                        <Badge variant="outline" className="text-[10px]">
                            Workflow #{config.workflowId}
                        </Badge>
                    )}
                </div>

                <ImportJsonDialog open={importOpen} onOpenChange={setImportOpen} />
            </div>
        </TooltipProvider>
    );
}
