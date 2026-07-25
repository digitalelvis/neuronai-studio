import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { Check, Copy } from 'lucide-react';
import CodeViewer from '@/components/code/CodeViewer';

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
            <div className="rounded-lg border border-border bg-card p-4">
                <p className="text-sm font-medium text-foreground">Connect to External Clients</p>
                <p className="mt-1.5 text-xs text-muted-foreground">
                    Save this {type} first to generate streaming integration endpoints.
                </p>
            </div>
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

    const UrlField = ({ label, value, copyKey }) => (
        <div className="space-y-1.5">
            <label className="text-xs font-medium text-muted-foreground">{label}</label>
            <div className="flex items-start gap-2">
                <code className="min-w-0 flex-1 break-all rounded-md border border-border bg-muted/50 px-3 py-2 font-mono text-xs leading-relaxed select-all">
                    {value}
                </code>
                <Button
                    variant="outline"
                    size="icon"
                    className="h-9 w-9 shrink-0"
                    onClick={() => handleCopy(value, copyKey)}
                >
                    {copiedKey === copyKey ? (
                        <Check className="h-3.5 w-3.5 text-green-500" />
                    ) : (
                        <Copy className="h-3.5 w-3.5" />
                    )}
                </Button>
            </div>
        </div>
    );

    return (
        <div className="flex flex-col gap-4 py-1">
            <div className="flex items-center justify-between gap-3">
                <div>
                    <p className="text-sm font-medium text-foreground">External Integration Endpoints</p>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        Use these URLs to stream from your own client.
                    </p>
                </div>
                <Badge variant="outline" className="shrink-0 text-[10px]">
                    {type.toUpperCase()}
                </Badge>
            </div>

            <Tabs defaultValue={defaultProtocol}>
                <TabsList className={`grid w-full ${activeProtocols.length > 1 ? 'grid-cols-2' : 'grid-cols-1'}`}>
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
                            <UrlField
                                label="Stream URL"
                                value={streamUrl}
                                copyKey={`stream-${protocol}`}
                            />

                            {resumeUrl && (
                                <UrlField
                                    label="Resume URL (Human node)"
                                    value={resumeUrl}
                                    copyKey={`resume-${protocol}`}
                                />
                            )}

                            <div className="space-y-2">
                                <div className="flex items-center justify-between gap-2">
                                    <label className="text-xs font-medium text-muted-foreground">
                                        Example Client Code
                                    </label>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="h-7 px-2 text-xs"
                                        onClick={() => handleCopy(snippet, `snippet-${protocol}`)}
                                    >
                                        {copiedKey === `snippet-${protocol}` ? (
                                            <span className="text-green-500">Copied!</span>
                                        ) : (
                                            'Copy Code'
                                        )}
                                    </Button>
                                </div>
                                <CodeViewer
                                    className="border border-border"
                                    height="320px"
                                    minHeight="280px"
                                    language="plaintext"
                                    value={snippet}
                                />
                            </div>
                        </TabsContent>
                    );
                })}
            </Tabs>
        </div>
    );
}
