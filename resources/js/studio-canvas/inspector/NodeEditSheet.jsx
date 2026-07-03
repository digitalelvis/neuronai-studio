import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import NodeConfigForm from './NodeConfigForm';
import { useNodeEditor } from './useNodeEditor';

export default function NodeEditSheet({
    agents = [],
    tools = [],
    mcpServers = [],
    knowledgeBases = [],
    ragSearchUrlTemplate = '',
    outputClasses = [],
    providers = {},
    providerModels = {},
    defaultProvider = '',
    defaultModel = '',
    readOnly = false,
}) {
    const { editingNode, sheetOpen, setSheetOpen, syncNode, removeNode } = useNodeEditor();

    const nodeType = editingNode?.type ?? 'node';
    const title = `Edit ${nodeType} node`;

    return (
        <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
            <SheetContent side="right" className="flex w-full flex-col overflow-hidden sm:max-w-md">
                <SheetHeader>
                    <SheetTitle className="capitalize">{title}</SheetTitle>
                    <SheetDescription>
                        {readOnly ? 'Read-only preview of node configuration.' : 'Configure node properties.'}
                    </SheetDescription>
                </SheetHeader>

                <ScrollArea className="flex-1 px-4 pb-4">
                    <NodeConfigForm
                        node={editingNode}
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
                        readOnly={readOnly}
                        onUpdate={readOnly ? undefined : syncNode}
                        onRemove={readOnly ? undefined : removeNode}
                    />
                </ScrollArea>
            </SheetContent>
        </Sheet>
    );
}
