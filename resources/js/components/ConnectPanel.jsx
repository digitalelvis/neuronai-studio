import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { Check, Copy } from 'lucide-react';

export default function ConnectPanel({
    protocols = ['vercel', 'agui'],
    streamUrls = {},
    resumeUrls = {},
    type = 'agent',
}) {
    const [copiedKey, setCopiedKey] = useState(null);

    const handleCopy = (text, key) => {
        if (!text) return;
        navigator.clipboard.writeText(text);
        setCopiedKey(key);
        setTimeout(() => setCopiedKey(null), 2000);
    };

    const activeProtocols = protocols.filter((p) => streamUrls[p]);

    if (activeProtocols.length === 0) {
        return (
            <Card className="m-4">
                <CardHeader>
                    <CardTitle className="text-sm">Connect to External Clients</CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-xs text-muted-foreground">
                        Save this {type} first to generate streaming integration endpoints.
                    </p>
                </CardContent>
            </Card>
        );
    }

    const defaultProtocol = activeProtocols[0];

    const generateSnippet = (protocol, streamUrl, resumeUrl) => {
        if (protocol === 'vercel') {
            return `import { useChat } from 'ai/react';

export function ChatComponent() {
  const { messages, input, handleInputChange, handleSubmit } = useChat({
    api: '${streamUrl}',
  });

  return (
    <div>
      {messages.map(m => (
        <div key={m.id}>{m.role}: {m.content}</div>
      ))}
      <form onSubmit={handleSubmit}>
        <input value={input} onChange={handleInputChange} placeholder="Say something..." />
        <button type="submit">Send</button>
      </form>
    </div>
  );
}`;
        }

        if (protocol === 'agui') {
            return `// AG-UI Protocol Integration
const response = await fetch('${streamUrl}', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ message: 'Hello' }),
});

const reader = response.body.getReader();
const decoder = new TextDecoder();

while (true) {
  const { done, value } = await reader.read();
  if (done) break;
  console.log(decoder.decode(value));
}
${resumeUrl ? `\n// Resume when awaiting input:\n// POST ${resumeUrl}` : ''}`;
        }

        return `// POST ${streamUrl}`;
    };

    return (
        <div className="flex h-full flex-col p-4 overflow-y-auto">
            <Card>
                <CardHeader className="pb-3">
                    <div className="flex items-center justify-between">
                        <CardTitle className="text-sm">External Integration Endpoints</CardTitle>
                        <Badge variant="outline" className="text-[10px]">
                            {type.toUpperCase()}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent className="space-y-4">
                    <Tabs defaultValue={defaultProtocol}>
                        <TabsList className="grid w-full grid-cols-2">
                            {activeProtocols.includes('vercel') && (
                                <TabsTrigger value="vercel">Vercel AI SDK</TabsTrigger>
                            )}
                            {activeProtocols.includes('agui') && (
                                <TabsTrigger value="agui">AG-UI</TabsTrigger>
                            )}
                        </TabsList>

                        {activeProtocols.map((protocol) => {
                            const streamUrl = streamUrls[protocol] || '';
                            const resumeUrl = resumeUrls[protocol] || '';
                            const snippet = generateSnippet(protocol, streamUrl, resumeUrl);

                            return (
                                <TabsContent key={protocol} value={protocol} className="mt-4 space-y-4">
                                    <div className="space-y-1.5">
                                        <label className="text-xs font-medium text-muted-foreground">Stream URL</label>
                                        <div className="flex items-center gap-2">
                                            <code className="flex-1 rounded border border-border bg-muted/50 px-2.5 py-1.5 font-mono text-xs select-all overflow-x-auto">
                                                {streamUrl}
                                            </code>
                                            <Button
                                                variant="outline"
                                                size="icon"
                                                className="h-8 w-8 shrink-0"
                                                onClick={() => handleCopy(streamUrl, `stream-${protocol}`)}
                                            >
                                                {copiedKey === `stream-${protocol}` ? (
                                                    <Check className="h-3.5 w-3.5 text-green-500" />
                                                ) : (
                                                    <Copy className="h-3.5 w-3.5" />
                                                )}
                                            </Button>
                                        </div>
                                    </div>

                                    {resumeUrl && (
                                        <div className="space-y-1.5">
                                            <label className="text-xs font-medium text-muted-foreground">Resume URL (Human node)</label>
                                            <div className="flex items-center gap-2">
                                                <code className="flex-1 rounded border border-border bg-muted/50 px-2.5 py-1.5 font-mono text-xs select-all overflow-x-auto">
                                                    {resumeUrl}
                                                </code>
                                                <Button
                                                    variant="outline"
                                                    size="icon"
                                                    className="h-8 w-8 shrink-0"
                                                    onClick={() => handleCopy(resumeUrl, `resume-${protocol}`)}
                                                >
                                                    {copiedKey === `resume-${protocol}` ? (
                                                        <Check className="h-3.5 w-3.5 text-green-500" />
                                                    ) : (
                                                        <Copy className="h-3.5 w-3.5" />
                                                    )}
                                                </Button>
                                            </div>
                                        </div>
                                    )}

                                    <div className="space-y-1.5">
                                        <div className="flex items-center justify-between">
                                            <label className="text-xs font-medium text-muted-foreground">Example Client Code</label>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="h-6 px-2 text-xs"
                                                onClick={() => handleCopy(snippet, `snippet-${protocol}`)}
                                            >
                                                {copiedKey === `snippet-${protocol}` ? (
                                                    <span className="text-green-500">Copied!</span>
                                                ) : (
                                                    'Copy Code'
                                                )}
                                            </Button>
                                        </div>
                                        <pre className="rounded border border-border bg-muted/60 p-3 font-mono text-xs text-foreground overflow-x-auto leading-relaxed">
                                            {snippet}
                                        </pre>
                                    </div>
                                </TabsContent>
                            );
                        })}
                    </Tabs>
                </CardContent>
            </Card>
        </div>
    );
}
