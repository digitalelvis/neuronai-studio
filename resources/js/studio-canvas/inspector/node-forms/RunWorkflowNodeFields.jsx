import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Label } from '@/components/ui/label';
import { isToolModeEnabled, isNodeTypeToolable, defaultToolExposure } from '../nodeUtils';
import { StateVariableTextField } from '../shared/state-variables';
import StateMapEditor from './editors/StateMapEditor';
import OutputKeyField from './fields/OutputKeyField';
import ToolModeToggle from './fields/ToolModeToggle';

export default function RunWorkflowNodeFields({
    node,
    data,
    workflows = [],
    typeMeta = {},
    readOnly = false,
    compact = false,
    showControls = true,
    onUpdate,
}) {
    if (!showControls) {
        return null;
    }

    const toolable = isNodeTypeToolable(typeMeta);
    const toolMode = isToolModeEnabled(data);

    const updateField = (key, value) => {
        onUpdate?.({ ...data, [key]: value });
    };

    const setToolMode = (enabled) => {
        if (!enabled) {
            onUpdate?.({ ...data, tool_mode: false });
            return;
        }

        onUpdate?.({
            ...data,
            tool_mode: true,
            tool_exposure: defaultToolExposure(data, typeMeta),
        });
    };

    const openActions = () => {
        window.dispatchEvent(
            new CustomEvent('canvas-tool-exposure-edit', {
                detail: { id: node.id, data, nodeType: node.type },
            }),
        );
    };

    return (
        <>
            {toolable && (
                <ToolModeToggle
                    nodeId={node.id}
                    toolMode={toolMode}
                    readOnly={readOnly}
                    onCheckedChange={setToolMode}
                />
            )}

            <div className="space-y-2">
                <Label>Workflow</Label>
                <Combobox
                    options={workflows.map((workflow) => ({
                        value: String(workflow.id),
                        label: workflow.slug
                            ? `${workflow.name} (${workflow.slug})`
                            : workflow.name,
                    }))}
                    value={data.workflow_id ? String(data.workflow_id) : ''}
                    onValueChange={(value) => updateField('workflow_id', value)}
                    placeholder="Select workflow"
                    searchPlaceholder="Search workflows…"
                    disabled={readOnly}
                />
            </div>

            <div className="space-y-2">
                <Label>{toolMode ? 'Default message' : 'Message'}</Label>
                <StateVariableTextField
                    rows={compact ? 2 : 3}
                    value={data.message ?? ''}
                    onChange={(e) => updateField('message', e.target.value)}
                    currentNodeId={node.id}
                    placeholder="{{input}}"
                    disabled={readOnly}
                    compact={compact}
                    label="Edit message"
                />
                {toolMode && !compact && (
                    <p className="text-xs text-muted-foreground">
                        Used when the supervisor does not pass input. Caller input wins.
                    </p>
                )}
            </div>

            <StateMapEditor
                data={data}
                readOnly={readOnly}
                onUpdate={onUpdate}
                compact={compact}
                currentNodeId={node.id}
            />

            {toolMode && (
                <div className="space-y-1" data-ab-handle-anchor="toolset">
                    <Label>Actions</Label>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="h-8 w-full justify-between text-[11px]"
                        disabled={readOnly}
                        onClick={openActions}
                    >
                        <span>
                            {(data.tool_exposure?.slug || typeMeta?.tool_exposure?.slug_prefix || 'run_workflow')}
                        </span>
                        <span className="text-muted-foreground">Edit</span>
                    </Button>
                    <p className="text-[10px] text-muted-foreground">
                        Slug and description for the supervisor tool call.
                    </p>
                </div>
            )}

            {!toolMode && (
                <OutputKeyField
                    value={data.output_key}
                    defaultValue="child_output"
                    onChange={(value) => updateField('output_key', value)}
                    readOnly={readOnly}
                    compact={compact}
                    hint="Parent state key where the child workflow output is stored."
                />
            )}
        </>
    );
}
