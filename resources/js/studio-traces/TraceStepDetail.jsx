import { useState } from 'react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

function JsonBlock({ data, mode = 'pretty' }) {
    const text =
        mode === 'pretty'
            ? typeof data === 'string'
                ? data
                : JSON.stringify(data, null, 2)
            : typeof data === 'string'
              ? data
              : JSON.stringify(data);

    return (
        <pre className="overflow-x-auto rounded-md border border-border bg-background p-4 font-mono text-xs">{text}</pre>
    );
}

export default function TraceStepDetail({ trace, selectedStep }) {
    const [viewMode, setViewMode] = useState('pretty');

    return (
        <Tabs defaultValue="input" className="flex h-full flex-col p-4">
            <div className="flex items-center justify-between gap-2">
                <TabsList>
                    <TabsTrigger value="input">Input</TabsTrigger>
                    <TabsTrigger value="output">Output</TabsTrigger>
                    <TabsTrigger value="step">Step State</TabsTrigger>
                </TabsList>
                <div className="flex gap-1">
                    <Button
                        type="button"
                        variant={viewMode === 'pretty' ? 'secondary' : 'ghost'}
                        size="sm"
                        className="h-7 text-xs"
                        onClick={() => setViewMode('pretty')}
                    >
                        Pretty
                    </Button>
                    <Button
                        type="button"
                        variant={viewMode === 'data' ? 'secondary' : 'ghost'}
                        size="sm"
                        className="h-7 text-xs"
                        onClick={() => setViewMode('data')}
                    >
                        Data
                    </Button>
                </div>
            </div>

            <TabsContent value="input" className={cn('mt-3 flex-1 overflow-auto')}>
                <JsonBlock data={trace?.input} mode={viewMode} />
            </TabsContent>
            <TabsContent value="output" className="mt-3 flex-1 overflow-auto">
                <JsonBlock data={trace?.output} mode={viewMode} />
            </TabsContent>
            <TabsContent value="step" className="mt-3 flex-1 overflow-auto">
                {selectedStep ? (
                    <JsonBlock data={selectedStep.state_snapshot} mode={viewMode} />
                ) : (
                    <p className="text-sm text-muted-foreground">Select a step from the timeline.</p>
                )}
            </TabsContent>
        </Tabs>
    );
}
