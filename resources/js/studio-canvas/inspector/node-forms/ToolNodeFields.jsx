import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Label } from '@/components/ui/label';
import OutputKeyField from './fields/OutputKeyField';
import ParametersJsonField from './fields/ParametersJsonField';

export default function ToolNodeFields({
    node,
    data,
    tools = [],
    readOnly = false,
    showControls = true,
    showAdvanced = true,
    onUpdate,
}) {
    const updateField = (key, value) => {
        onUpdate?.({ ...data, [key]: value });
    };

    const openToolActions = () => {
        window.dispatchEvent(
            new CustomEvent('canvas-tool-actions-edit', {
                detail: {
                    id: node.id,
                    data,
                    toolRef: data.tool_ref || '',
                },
            }),
        );
    };

    const toolOptions = tools.map((tool) => ({
        value: tool.ref,
        label: tool.label || tool.ref,
    }));

    return (
        <>
            {showControls && (
                <>
                    <div className="space-y-2">
                        <Label>Tool</Label>
                        <Combobox
                            options={toolOptions}
                            value={data.tool_ref ?? ''}
                            onValueChange={(value) => updateField('tool_ref', value)}
                            placeholder="Select tool"
                            searchPlaceholder="Search tools…"
                            emptyText="No tools found."
                            disabled={readOnly}
                        />
                    </div>
                    <OutputKeyField
                        value={data.output_key}
                        defaultValue="tool_result"
                        onChange={(value) => updateField('output_key', value)}
                        readOnly={readOnly}
                    />
                    <div className="space-y-1">
                        <Label>Actions</Label>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            className="h-8 w-full justify-between text-[11px]"
                            disabled={!data.tool_ref}
                            onClick={openToolActions}
                        >
                            <span className="truncate">
                                {data.tool_ref
                                    ? tools.find((tool) => tool.ref === data.tool_ref)?.label ||
                                      data.tool_ref
                                    : 'Select a tool'}
                            </span>
                            <span className="text-muted-foreground">
                                {tools.find((tool) => tool.ref === data.tool_ref)?.editable
                                    ? 'Edit'
                                    : 'View'}
                            </span>
                        </Button>
                        <p className="text-[10px] text-muted-foreground">
                            Slug, description, and parameters (ToolInterface schema).
                        </p>
                    </div>
                </>
            )}
            {showAdvanced && (
                <ParametersJsonField data={data} readOnly={readOnly} onUpdate={onUpdate} />
            )}
        </>
    );
}
