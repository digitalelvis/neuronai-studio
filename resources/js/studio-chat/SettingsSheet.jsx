import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Separator } from '@/components/ui/separator';
import { Badge } from '@/components/ui/badge';
import StudioPlayground from './StudioPlayground';

export default function SettingsSheet({ open, onOpenChange, mode, entityId, context, onContextChange, agentMeta }) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="flex w-full flex-col overflow-hidden sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>Settings</SheetTitle>
                    <SheetDescription>
                        {mode === 'workflow' ? 'Configure initial workflow state.' : 'Agent configuration and context.'}
                    </SheetDescription>
                </SheetHeader>

                {agentMeta && (
                    <div className="space-y-2 py-2">
                        <p className="text-sm font-medium">{agentMeta.name}</p>
                        <div className="flex flex-wrap gap-1.5">
                            <Badge variant="secondary">{agentMeta.provider}</Badge>
                            <Badge variant="outline">{agentMeta.model}</Badge>
                        </div>
                        {agentMeta.tools?.length > 0 && (
                            <p className="text-xs text-muted-foreground">Tools: {agentMeta.tools.join(', ')}</p>
                        )}
                        {agentMeta.mcpServers?.length > 0 && (
                            <p className="text-xs text-muted-foreground">MCP: {agentMeta.mcpServers.join(', ')}</p>
                        )}
                    </div>
                )}

                <Separator />

                <div className="flex-1 overflow-auto py-4">
                    <StudioPlayground
                        mode={mode}
                        entityId={entityId}
                        context={context}
                        onContextChange={onContextChange}
                        variant="sheet"
                    />
                </div>
            </SheetContent>
        </Sheet>
    );
}
