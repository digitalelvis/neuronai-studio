import { useEffect, useRef, useState } from 'react';
import { Braces, ImagePlus, Send } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

const ACCEPT_MAP = {
    image: 'image/*',
    audio: 'audio/*',
    video: 'video/*',
    document: '*/*',
};

function detectType(file) {
    if (file.type.startsWith('image/')) return 'image';
    if (file.type.startsWith('audio/')) return 'audio';
    if (file.type.startsWith('video/')) return 'video';
    return 'document';
}

export default function Composer({
    disabled,
    onSend,
    enableAttachments = false,
    enableInputJson = false,
    inputJson = '{}',
    onInputJsonChange,
    inputJsonError = '',
}) {
    const [text, setText] = useState('');
    const [attachments, setAttachments] = useState([]);
    const [inputOpen, setInputOpen] = useState(false);
    const fileRef = useRef(null);
    const previewUrlsRef = useRef([]);

    useEffect(() => {
        return () => {
            previewUrlsRef.current.forEach((url) => URL.revokeObjectURL(url));
            previewUrlsRef.current = [];
        };
    }, []);

    const hasCustomInput = enableInputJson && inputJson.trim() !== '{}' && inputJson.trim() !== '';
    const sendDisabled = disabled || (enableInputJson && Boolean(inputJsonError));
    const canSubmit = !sendDisabled && (Boolean(text.trim()) || attachments.length > 0);

    const handleSubmit = (event) => {
        event.preventDefault();
        if (!canSubmit) {
            return;
        }

        onSend(text.trim(), attachments);
        setText('');
        setAttachments([]);
    };

    const handleKeyDown = (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            handleSubmit(event);
        }
    };

    const handleFiles = (files) => {
        const next = Array.from(files).map((file) => {
            const previewUrl = file.type.startsWith('image/') ? URL.createObjectURL(file) : null;
            if (previewUrl) {
                previewUrlsRef.current.push(previewUrl);
            }

            return {
                id: `${file.name}-${file.lastModified}`,
                type: detectType(file),
                name: file.name,
                mimeType: file.type,
                file,
                previewUrl,
            };
        });

        setAttachments((current) => [...current, ...next]);
    };

    return (
        <form className="mx-auto w-full max-w-3xl space-y-2" onSubmit={handleSubmit}>
            {enableInputJson && inputOpen && (
                <div className="space-y-1">
                    <Textarea
                        rows={4}
                        placeholder='{"key": "value"}'
                        value={inputJson}
                        disabled={disabled}
                        onChange={(event) => onInputJsonChange?.(event.target.value)}
                        className="resize-none font-mono text-xs"
                    />
                    {inputJsonError && <p className="text-xs text-destructive">{inputJsonError}</p>}
                </div>
            )}
            {attachments.length > 0 && (
                <div className="flex flex-wrap gap-2 px-1">
                    {attachments.map((attachment) => (
                        <span
                            key={attachment.id}
                            className="inline-flex items-center gap-1 rounded-md border border-border bg-muted px-2 py-1 text-xs"
                        >
                            {attachment.name}
                            <button
                                type="button"
                                className="text-muted-foreground hover:text-foreground"
                                onClick={() => {
                                    if (attachment.previewUrl) {
                                        URL.revokeObjectURL(attachment.previewUrl);
                                        previewUrlsRef.current = previewUrlsRef.current.filter(
                                            (url) => url !== attachment.previewUrl,
                                        );
                                    }
                                    setAttachments((current) => current.filter((item) => item.id !== attachment.id));
                                }}
                            >
                                ×
                            </button>
                        </span>
                    ))}
                </div>
            )}
            <div className="rounded-xl border border-border bg-card shadow-sm focus-within:ring-1 focus-within:ring-ring">
                <Textarea
                    rows={3}
                    placeholder="Send a message..."
                    value={text}
                    disabled={disabled}
                    onChange={(event) => setText(event.target.value)}
                    onKeyDown={handleKeyDown}
                    className="min-h-[72px] resize-none border-0 bg-transparent px-4 pt-3 shadow-none focus-visible:ring-0"
                />
                <div className="flex items-center justify-between gap-2 px-3 pb-3">
                    <div className="flex items-center gap-1">
                        {enableInputJson && (
                            <Button
                                type="button"
                                variant={inputOpen ? 'secondary' : 'ghost'}
                                size="icon"
                                disabled={disabled}
                                onClick={() => setInputOpen((open) => !open)}
                                className={cn('h-8 w-8', hasCustomInput && !inputOpen && 'text-primary')}
                                title="Initial state JSON"
                            >
                                <Braces className="h-4 w-4" />
                            </Button>
                        )}
                        {enableAttachments && (
                            <>
                                <input
                                    ref={fileRef}
                                    type="file"
                                    multiple
                                    hidden
                                    accept={Object.values(ACCEPT_MAP).join(',')}
                                    onChange={(event) => {
                                        if (event.target.files?.length) {
                                            handleFiles(event.target.files);
                                        }
                                        event.target.value = '';
                                    }}
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    disabled={disabled}
                                    onClick={() => fileRef.current?.click()}
                                    className="h-8 w-8"
                                    title="Attach file"
                                >
                                    <ImagePlus className="h-4 w-4" />
                                </Button>
                            </>
                        )}
                    </div>
                    <Button type="submit" size="sm" disabled={!canSubmit} className="min-w-[72px]">
                        {disabled ? (
                            'Sending…'
                        ) : (
                            <>
                                <Send className="h-3.5 w-3.5" />
                                Send
                            </>
                        )}
                    </Button>
                </div>
            </div>
        </form>
    );
}
