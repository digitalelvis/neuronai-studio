import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export default function RagFields({
    data = {},
    knowledgeBases = [],
    ragSearchUrlTemplate = '',
    readOnly = false,
    onChange,
}) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(false);

    const knowledgeBaseId = data.knowledge_base_id ? String(data.knowledge_base_id) : '';

    const updateNumber = (key, value) => {
        onChange?.({ [key]: value === '' ? undefined : Number(value) });
    };

    const runPreview = async () => {
        if (!knowledgeBaseId || !ragSearchUrlTemplate) {
            return;
        }

        setLoading(true);
        setError(null);
        setResults([]);

        try {
            const url = ragSearchUrlTemplate.replace('__KB__', knowledgeBaseId);
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    query: query || data.query || '',
                    top_k: data.top_k ?? undefined,
                    threshold: data.threshold ?? undefined,
                }),
            });

            const payload = await response.json();

            if (!response.ok) {
                setError(payload.error ?? 'Search failed.');
                return;
            }

            setResults(payload.results ?? []);

            if ((payload.results ?? []).length === 0) {
                setError('No matching chunks. Ingest documents or adjust the threshold.');
            }
        } catch (e) {
            setError(e.message ?? 'Search failed.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="space-y-4">
            <div className="space-y-2">
                <Label>Knowledge Base</Label>
                <Select
                    value={knowledgeBaseId}
                    onValueChange={(value) => onChange?.({ knowledge_base_id: value })}
                    disabled={readOnly || knowledgeBases.length === 0}
                >
                    <SelectTrigger>
                        <SelectValue placeholder={knowledgeBases.length === 0 ? 'No knowledge bases' : 'Select knowledge base'} />
                    </SelectTrigger>
                    <SelectContent>
                        {knowledgeBases.map((kb) => (
                            <SelectItem key={kb.id} value={String(kb.id)}>
                                {kb.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {knowledgeBases.length === 0 && (
                    <p className="text-xs text-muted-foreground">Create a knowledge base under Knowledge Bases first.</p>
                )}
            </div>

            <div className="space-y-2">
                <Label>Query template</Label>
                <Textarea
                    rows={2}
                    value={data.query ?? ''}
                    onChange={(e) => onChange?.({ query: e.target.value })}
                    placeholder="{{ input }}"
                    disabled={readOnly}
                />
                <p className="text-xs text-muted-foreground">
                    Interpolated against workflow state. Falls back to {'{{ input }}'} when empty.
                </p>
            </div>

            <div className="grid grid-cols-2 gap-3">
                <div className="space-y-2">
                    <Label>Top K</Label>
                    <Input
                        type="number"
                        min={1}
                        value={data.top_k ?? ''}
                        onChange={(e) => updateNumber('top_k', e.target.value)}
                        placeholder="5"
                        disabled={readOnly}
                    />
                </div>
                <div className="space-y-2">
                    <Label>Threshold</Label>
                    <Input
                        type="number"
                        step="0.01"
                        min={0}
                        max={1}
                        value={data.threshold ?? ''}
                        onChange={(e) => updateNumber('threshold', e.target.value)}
                        placeholder="none"
                        disabled={readOnly}
                    />
                </div>
            </div>

            <div className="space-y-2">
                <Label>Output Key</Label>
                <Input
                    value={data.output_key ?? 'rag_context'}
                    onChange={(e) => onChange?.({ output_key: e.target.value })}
                    disabled={readOnly}
                />
                <p className="text-xs text-muted-foreground">
                    State key holding {'{ query, results, context, top_score }'}. Read downstream via {'{{ rag_context.context }}'}.
                </p>
            </div>

            {ragSearchUrlTemplate && (
                <div className="space-y-2 rounded-md border border-border bg-muted/20 p-3">
                    <Label className="text-xs text-muted-foreground">Retrieval preview</Label>
                    <div className="flex gap-2">
                        <Input
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Test query…"
                            disabled={readOnly || !knowledgeBaseId}
                        />
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={runPreview}
                            disabled={loading || !knowledgeBaseId}
                        >
                            {loading ? 'Searching…' : 'Preview'}
                        </Button>
                    </div>

                    {error && <p className="text-xs text-muted-foreground">{error}</p>}

                    {results.length > 0 && (
                        <ul className="space-y-2">
                            {results.map((result, index) => (
                                <li key={result.id ?? index} className="rounded-md border border-border bg-background p-2 text-xs">
                                    <div className="mb-1 flex items-center justify-between">
                                        <span className="font-mono text-muted-foreground">{result.source_name || 'chunk'}</span>
                                        <span className="text-muted-foreground">{Number(result.score ?? 0).toFixed(3)}</span>
                                    </div>
                                    <p className="text-muted-foreground">{(result.content ?? '').slice(0, 200)}</p>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
        </div>
    );
}
