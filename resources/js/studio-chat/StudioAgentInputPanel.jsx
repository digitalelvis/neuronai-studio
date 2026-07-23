import { useEffect, useState } from 'react';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Input } from '@/components/ui/input';
import Composer from './Composer';

export default function StudioAgentInputPanel({
    instructions,
    onInstructionsChange,
    context,
    onContextChange,
    parameters,
    onParametersChange,
    onSend,
    sending = false,
    enableAttachments = false,
    hideComposer = false,
}) {
    const [inputJson, setInputJson] = useState(() => JSON.stringify(context ?? {}, null, 2));
    const [inputJsonError, setInputJsonError] = useState('');

    useEffect(() => {
        setInputJson(JSON.stringify(context ?? {}, null, 2));
    }, [context]);

    const handleInputJsonChange = (value) => {
        setInputJson(value);

        try {
            const parsed = JSON.parse(value || '{}');
            setInputJsonError('');
            onContextChange?.(parsed);
        } catch {
            setInputJsonError('Invalid JSON');
        }
    };

    const updateParameter = (key, value) => {
        onParametersChange?.({
            ...(parameters ?? {}),
            [key]: value === '' ? null : value,
        });
    };

    return (
        <div className="flex h-full flex-col gap-4 overflow-hidden">
            <div className="space-y-2">
                <Label htmlFor="playground-instructions">System prompt</Label>
                <Textarea
                    id="playground-instructions"
                    className="min-h-[160px] resize-y font-mono text-xs"
                    value={instructions}
                    onChange={(event) => onInstructionsChange?.(event.target.value)}
                    placeholder="Agent instructions…"
                />
            </div>

            <div className="space-y-2">
                <Label>Model parameters</Label>
                <div className="grid grid-cols-3 gap-2">
                    <div className="space-y-1">
                        <Label htmlFor="param-temperature" className="text-[11px] text-muted-foreground">
                            Temperature
                        </Label>
                        <Input
                            id="param-temperature"
                            type="number"
                            min="0"
                            max="2"
                            step="0.1"
                            placeholder="default"
                            value={parameters?.temperature ?? ''}
                            onChange={(event) => updateParameter('temperature', event.target.value)}
                            className="h-8 text-xs"
                        />
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="param-top-p" className="text-[11px] text-muted-foreground">
                            Top P
                        </Label>
                        <Input
                            id="param-top-p"
                            type="number"
                            min="0"
                            max="1"
                            step="0.05"
                            placeholder="default"
                            value={parameters?.top_p ?? ''}
                            onChange={(event) => updateParameter('top_p', event.target.value)}
                            className="h-8 text-xs"
                        />
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="param-max-tokens" className="text-[11px] text-muted-foreground">
                            Max tokens
                        </Label>
                        <Input
                            id="param-max-tokens"
                            type="number"
                            min="1"
                            step="1"
                            placeholder="default"
                            value={parameters?.max_tokens ?? ''}
                            onChange={(event) => updateParameter('max_tokens', event.target.value)}
                            className="h-8 text-xs"
                        />
                    </div>
                </div>
                <p className="text-[11px] text-muted-foreground">
                    NeuronAI does not expose model fine-tuning here; these inference parameters are passed to the provider per request.
                </p>
            </div>

            {!hideComposer && (
                <div className="mt-auto border-t border-border pt-4">
                    <Composer
                        disabled={sending}
                        onSend={onSend}
                        enableAttachments={enableAttachments}
                        enableInputJson
                        inputJson={inputJson}
                        onInputJsonChange={handleInputJsonChange}
                        inputJsonError={inputJsonError}
                    />
                </div>
            )}

            {hideComposer && (
                <div className="space-y-2 border-t border-border pt-4">
                    <Label>Context JSON</Label>
                    <Textarea
                        rows={4}
                        placeholder='{"key": "value"}'
                        value={inputJson}
                        onChange={(event) => handleInputJsonChange(event.target.value)}
                        className="resize-none font-mono text-xs"
                    />
                    {inputJsonError && <p className="text-xs text-destructive">{inputJsonError}</p>}
                </div>
            )}
        </div>
    );
}
