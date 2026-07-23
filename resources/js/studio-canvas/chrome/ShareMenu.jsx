import { useState } from 'react';
import { ChevronDown, Code2, Download, Share2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { ScrollArea } from '@/components/ui/scroll-area';
import ConnectPanel from '../../components/ConnectPanel';
import GraphJsonPanel from '../GraphJsonPanel';
import WorkflowCodePanel from '../WorkflowCodePanel';
import { downloadWorkflowJson } from '../graphJson';

export default function ShareMenu({ workflowConfig = {} }) {
    const [panel, setPanel] = useState(null);

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="outline" size="sm" className="ab-fab ab-fab-share gap-1.5 shadow-lg">
                        <Share2 className="h-3.5 w-3.5" />
                        Share
                        <ChevronDown className="h-3.5 w-3.5 opacity-70" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-48">
                    <DropdownMenuItem onSelect={() => setPanel('connect')}>Connect / API</DropdownMenuItem>
                    <DropdownMenuItem onSelect={() => setPanel('code')}>
                        <Code2 className="mr-2 h-3.5 w-3.5" />
                        Export PHP
                    </DropdownMenuItem>
                    <DropdownMenuItem onSelect={() => setPanel('json')}>Graph JSON</DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem onSelect={() => downloadWorkflowJson(false)}>
                        <Download className="mr-2 h-3.5 w-3.5" />
                        Download JSON
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <Sheet open={panel !== null} onOpenChange={(open) => !open && setPanel(null)}>
                <SheetContent side="right" className="flex w-full flex-col overflow-hidden sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>
                            {panel === 'connect' && 'Connect'}
                            {panel === 'code' && 'Export PHP'}
                            {panel === 'json' && 'Graph JSON'}
                        </SheetTitle>
                        <SheetDescription>
                            {panel === 'connect' && 'Stream endpoints and integration snippets.'}
                            {panel === 'code' && 'Live PHP export preview for this workflow.'}
                            {panel === 'json' && 'Inspect or edit the raw graph JSON.'}
                        </SheetDescription>
                    </SheetHeader>
                    <ScrollArea className="flex-1 px-1 pb-4">
                        {panel === 'connect' && (
                            <ConnectPanel
                                protocols={workflowConfig.enabledProtocols ?? ['vercel', 'agui']}
                                streamUrls={workflowConfig.integrateStreamUrls ?? {}}
                                resumeUrls={workflowConfig.integrateResumeUrls ?? {}}
                                type="workflow"
                            />
                        )}
                        {panel === 'code' && (
                            <WorkflowCodePanel readOnly={workflowConfig.readOnly ?? false} />
                        )}
                        {panel === 'json' && (
                            <div className="p-2">
                                <GraphJsonPanel readOnly={workflowConfig.readOnly ?? false} />
                            </div>
                        )}
                    </ScrollArea>
                </SheetContent>
            </Sheet>
        </>
    );
}
